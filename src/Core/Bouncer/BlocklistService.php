<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Manages IP blocklist with Redis caching.
 *
 * Blocklist is populated from:
 * - Honeypot critical hits (/admin probing) - requires human review
 * - Manual entries - immediately active
 *
 * Uses a Bloom filter-style approach: cache the blocklist as a set
 * for O(1) lookups, rebuild periodically from database.
 *
 * ## Blocking Statuses
 *
 * | Status | Description |
 * |--------|-------------|
 * | `pending` | From honeypot, awaiting human review |
 * | `approved` | Active block (manual or reviewed) |
 * | `rejected` | Reviewed and rejected (not blocked) |
 *
 * ## Honeypot Integration
 *
 * When `auto_block_critical` is enabled (default), IPs hitting critical
 * honeypot paths are immediately blocked. Otherwise, they're added with
 * 'pending' status for human review.
 *
 * ### Syncing from Honeypot
 *
 * Call `syncFromHoneypot()` from a scheduled job to create pending entries
 * for critical hits from the last 24 hours:
 *
 * ```php
 * // In app/Console/Kernel.php
 * $schedule->call(function () {
 *     app(BlocklistService::class)->syncFromHoneypot();
 * })->hourly();
 * ```
 *
 * ### Reviewing Pending Blocks
 *
 * ```php
 * $blocklist = app(BlocklistService::class);
 *
 * // Get all pending entries (paginated for large blocklists)
 * $pending = $blocklist->getPending(perPage: 50);
 *
 * // Approve a block
 * $blocklist->approve('192.168.1.100');
 *
 * // Reject a block (IP will not be blocked)
 * $blocklist->reject('192.168.1.100');
 * ```
 *
 * ## Cache Behaviour
 *
 * - Blocklist is cached for 5 minutes (CACHE_TTL constant)
 * - Only 'approved' entries with valid expiry are included in cache
 * - Cache is automatically cleared on block/unblock/approve operations
 * - Use `clearCache()` to force cache refresh
 *
 * ## Manual Blocking
 *
 * ```php
 * $blocklist = app(BlocklistService::class);
 *
 * // Block an IP immediately (approved status)
 * $blocklist->block('192.168.1.100', 'spam', BlocklistService::STATUS_APPROVED);
 *
 * // Unblock an IP
 * $blocklist->unblock('192.168.1.100');
 *
 * // Check if IP is blocked
 * if ($blocklist->isBlocked('192.168.1.100')) {
 *     // IP is actively blocked
 * }
 * ```
 *
 * @see Boot For honeypot configuration options
 * @see BouncerMiddleware For the blocking middleware
 */
class BlocklistService
{
    protected const CACHE_KEY = 'bouncer:blocklist';

    protected const CACHE_TTL = 300; // 5 minutes

    protected const DEFAULT_PER_PAGE = 50;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Check if IP is blocked.
     */
    public function isBlocked(string $ip): bool
    {
        $blocklist = $this->getBlocklist();

        return isset($blocklist[$ip]);
    }

    /**
     * Add IP to blocklist (immediately approved for manual blocks).
     */
    public function block(string $ip, string $reason = 'manual', string $status = self::STATUS_APPROVED): void
    {
        DB::table('blocked_ips')->updateOrInsert(
            ['ip_address' => $ip],
            [
                'reason' => $reason,
                'status' => $status,
                'blocked_at' => now(),
                'expires_at' => now()->addDays(30),
            ]
        );

        $this->clearCache();
    }

    /**
     * Remove IP from blocklist.
     */
    public function unblock(string $ip): void
    {
        DB::table('blocked_ips')->where('ip_address', $ip)->delete();
        $this->clearCache();
    }

    /**
     * Get full blocklist (cached). Only returns approved entries.
     *
     * Used for O(1) IP lookup checks. For admin UIs with large blocklists,
     * use getBlocklistPaginated() instead.
     */
    public function getBlocklist(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            if (! $this->tableExists()) {
                return [];
            }

            return DB::table('blocked_ips')
                ->where('status', self::STATUS_APPROVED)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->pluck('reason', 'ip_address')
                ->toArray();
        });
    }

    /**
     * Get paginated blocklist for admin UI.
     *
     * Returns all entries (approved, pending, rejected) with pagination.
     * Use this for admin interfaces displaying large blocklists.
     *
     * @param  int|null  $perPage  Number of entries per page (default: 50)
     * @param  string|null  $status  Filter by status (null for all statuses)
     */
    public function getBlocklistPaginated(?int $perPage = null, ?string $status = null): LengthAwarePaginator
    {
        if (! $this->tableExists()) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage ?? self::DEFAULT_PER_PAGE);
        }

        $query = DB::table('blocked_ips')
            ->orderBy('blocked_at', 'desc');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage ?? self::DEFAULT_PER_PAGE);
    }

    /**
     * Check if the blocked_ips table exists.
     */
    protected function tableExists(): bool
    {
        return Cache::remember('bouncer:blocked_ips_table_exists', 3600, function (): bool {
            return DB::getSchemaBuilder()->hasTable('blocked_ips');
        });
    }

    /**
     * Sync blocklist from honeypot critical hits.
     *
     * Creates entries in 'pending' status for human review.
     * Call this from a scheduled job or after honeypot hits.
     */
    public function syncFromHoneypot(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('honeypot_hits')) {
            return 0;
        }

        $criticalIps = DB::table('honeypot_hits')
            ->where('severity', 'critical')
            ->where('created_at', '>=', now()->subDay())
            ->distinct()
            ->pluck('ip_address');

        $count = 0;
        foreach ($criticalIps as $ip) {
            $exists = DB::table('blocked_ips')
                ->where('ip_address', $ip)
                ->exists();

            if (! $exists) {
                DB::table('blocked_ips')->insert([
                    'ip_address' => $ip,
                    'reason' => 'honeypot_critical',
                    'status' => self::STATUS_PENDING,
                    'blocked_at' => now(),
                    'expires_at' => now()->addDays(7),
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get pending entries awaiting human review.
     *
     * @param  int|null  $perPage  Number of entries per page. Pass null for all entries (legacy behavior).
     * @return array|LengthAwarePaginator Array if $perPage is null, paginator otherwise.
     */
    public function getPending(?int $perPage = null): array|LengthAwarePaginator
    {
        if (! $this->tableExists()) {
            return $perPage === null
                ? []
                : new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $query = DB::table('blocked_ips')
            ->where('status', self::STATUS_PENDING)
            ->orderBy('blocked_at', 'desc');

        if ($perPage === null) {
            return $query->get()->toArray();
        }

        return $query->paginate($perPage);
    }

    /**
     * Approve a pending block entry.
     */
    public function approve(string $ip): bool
    {
        $updated = DB::table('blocked_ips')
            ->where('ip_address', $ip)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_APPROVED]);

        if ($updated > 0) {
            $this->clearCache();
        }

        return $updated > 0;
    }

    /**
     * Reject a pending block entry.
     */
    public function reject(string $ip): bool
    {
        $updated = DB::table('blocked_ips')
            ->where('ip_address', $ip)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_REJECTED]);

        return $updated > 0;
    }

    /**
     * Clear the cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get stats for dashboard.
     */
    public function getStats(): array
    {
        if (! $this->tableExists()) {
            return [
                'total_blocked' => 0,
                'active_blocked' => 0,
                'pending_review' => 0,
                'by_reason' => [],
                'by_status' => [],
            ];
        }

        return [
            'total_blocked' => DB::table('blocked_ips')->count(),
            'active_blocked' => DB::table('blocked_ips')
                ->where('status', self::STATUS_APPROVED)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->count(),
            'pending_review' => DB::table('blocked_ips')
                ->where('status', self::STATUS_PENDING)
                ->count(),
            'by_reason' => DB::table('blocked_ips')
                ->selectRaw('reason, COUNT(*) as count')
                ->groupBy('reason')
                ->pluck('count', 'reason')
                ->toArray(),
            'by_status' => DB::table('blocked_ips')
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
        ];
    }
}
