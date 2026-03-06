<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SEO redirect manager with caching.
 *
 * Handles 301/302 redirects early in the request lifecycle
 * before Laravel does heavy processing.
 */
class RedirectService
{
    protected const CACHE_KEY = 'bouncer:redirects';

    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Match a path against redirects.
     *
     * @return array{to: string, status: int}|null
     */
    public function match(string $path): ?array
    {
        $redirects = $this->getRedirects();
        $path = '/'.ltrim($path, '/');

        // Exact match first
        if (isset($redirects[$path])) {
            return $redirects[$path];
        }

        // Wildcard matches (path/*)
        foreach ($redirects as $from => $redirect) {
            if (str_ends_with($from, '*')) {
                $prefix = rtrim($from, '*');
                if (str_starts_with($path, $prefix)) {
                    // Replace the matched portion
                    $suffix = substr($path, strlen($prefix));
                    $to = str_ends_with($redirect['to'], '*')
                        ? rtrim($redirect['to'], '*').$suffix
                        : $redirect['to'];

                    return ['to' => $to, 'status' => $redirect['status']];
                }
            }
        }

        return null;
    }

    /**
     * Get all redirects (cached).
     *
     * @return array<string, array{to: string, status: int}>
     */
    public function getRedirects(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            if (! $this->tableExists()) {
                return [];
            }

            return DB::table('seo_redirects')
                ->where('active', true)
                ->get()
                ->keyBy('from_path')
                ->map(fn ($row) => [
                    'to' => $row->to_path,
                    'status' => $row->status_code,
                ])
                ->toArray();
        });
    }

    /**
     * Add a redirect.
     */
    public function add(string $from, string $to, int $status = 301): void
    {
        DB::table('seo_redirects')->updateOrInsert(
            ['from_path' => $from],
            [
                'to_path' => $to,
                'status_code' => $status,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->clearCache();
    }

    /**
     * Remove a redirect.
     */
    public function remove(string $from): void
    {
        DB::table('seo_redirects')->where('from_path', $from)->delete();
        $this->clearCache();
    }

    /**
     * Clear the cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Check if redirects table exists.
     */
    protected function tableExists(): bool
    {
        return Cache::remember('bouncer:redirects_table_exists', 3600, function () {
            return DB::getSchemaBuilder()->hasTable('seo_redirects');
        });
    }
}
