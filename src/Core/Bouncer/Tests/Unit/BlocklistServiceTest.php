<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer\Tests\Unit;

use Core\Bouncer\BlocklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

class BlocklistServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BlocklistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BlocklistService;
    }

    protected function defineDatabaseMigrations(): void
    {
        // Create blocked_ips table for testing
        Schema::create('blocked_ips', function ($table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('ip_range', 18)->nullable();
            $table->string('reason')->nullable();
            $table->string('source', 32)->default('manual');
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->unique(['ip_address', 'ip_range']);
            $table->index(['status', 'expires_at']);
            $table->index('ip_address');
        });

        // Create honeypot_hits table for testing syncFromHoneypot
        Schema::create('honeypot_hits', function ($table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('path');
            $table->string('severity', 32)->default('low');
            $table->timestamps();
        });
    }

    // =========================================================================
    // Blocking Tests
    // =========================================================================

    public function test_block_adds_ip_to_blocklist(): void
    {
        $this->service->block('192.168.1.100', 'test_reason');

        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '192.168.1.100',
            'reason' => 'test_reason',
            'status' => BlocklistService::STATUS_APPROVED,
        ]);
    }

    public function test_block_with_custom_status(): void
    {
        $this->service->block('192.168.1.100', 'honeypot', BlocklistService::STATUS_PENDING);

        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '192.168.1.100',
            'reason' => 'honeypot',
            'status' => BlocklistService::STATUS_PENDING,
        ]);
    }

    public function test_block_updates_existing_entry(): void
    {
        // First block
        $this->service->block('192.168.1.100', 'first_reason');

        // Second block should update
        $this->service->block('192.168.1.100', 'updated_reason');

        $this->assertDatabaseCount('blocked_ips', 1);
        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '192.168.1.100',
            'reason' => 'updated_reason',
        ]);
    }

    public function test_block_clears_cache(): void
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('bouncer:blocklist');

        Cache::shouldReceive('remember')
            ->andReturn([]);

        $this->service->block('192.168.1.100', 'test');
    }

    // =========================================================================
    // Unblocking Tests
    // =========================================================================

    public function test_unblock_removes_ip_from_blocklist(): void
    {
        $this->service->block('192.168.1.100', 'test');
        $this->service->unblock('192.168.1.100');

        $this->assertDatabaseMissing('blocked_ips', [
            'ip_address' => '192.168.1.100',
        ]);
    }

    public function test_unblock_clears_cache(): void
    {
        // First add the IP
        DB::table('blocked_ips')->insert([
            'ip_address' => '192.168.1.100',
            'reason' => 'test',
            'status' => BlocklistService::STATUS_APPROVED,
            'blocked_at' => now(),
        ]);

        Cache::shouldReceive('forget')
            ->once()
            ->with('bouncer:blocklist');

        $this->service->unblock('192.168.1.100');
    }

    public function test_unblock_does_not_fail_on_non_existent_ip(): void
    {
        // This should not throw an exception
        $this->service->unblock('192.168.1.200');

        $this->assertTrue(true);
    }

    // =========================================================================
    // IP Blocked Check Tests
    // =========================================================================

    public function test_is_blocked_returns_true_for_blocked_ip(): void
    {
        DB::table('blocked_ips')->insert([
            'ip_address' => '192.168.1.100',
            'reason' => 'test',
            'status' => BlocklistService::STATUS_APPROVED,
            'blocked_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        // Clear any existing cache
        Cache::forget('bouncer:blocklist');
        Cache::forget('bouncer:blocked_ips_table_exists');

        $this->assertTrue($this->service->isBlocked('192.168.1.100'));
    }

    public function test_is_blocked_returns_false_for_non_blocked_ip(): void
    {
        Cache::forget('bouncer:blocklist');
        Cache::forget('bouncer:blocked_ips_table_exists');

        $this->assertFalse($this->service->isBlocked('192.168.1.200'));
    }

    public function test_is_blocked_returns_false_for_expired_block(): void
    {
        DB::table('blocked_ips')->insert([
            'ip_address' => '192.168.1.100',
            'reason' => 'test',
            'status' => BlocklistService::STATUS_APPROVED,
            'blocked_at' => now()->subDays(2),
            'expires_at' => now()->subDay(), // Expired yesterday
        ]);

        Cache::forget('bouncer:blocklist');
        Cache::forget('bouncer:blocked_ips_table_exists');

        $this->assertFalse($this->service->isBlocked('192.168.1.100'));
    }

    public function test_is_blocked_returns_false_for_pending_status(): void
    {
        DB::table('blocked_ips')->insert([
            'ip_address' => '192.168.1.100',
            'reason' => 'test',
            'status' => BlocklistService::STATUS_PENDING,
            'blocked_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        Cache::forget('bouncer:blocklist');
        Cache::forget('bouncer:blocked_ips_table_exists');

        $this->assertFalse($this->service->isBlocked('192.168.1.100'));
    }

    public function test_is_blocked_returns_false_for_rejected_status(): void
    {
        DB::table('blocked_ips')->insert([
            'ip_address' => '192.168.1.100',
            'reason' => 'test',
            'status' => BlocklistService::STATUS_REJECTED,
            'blocked_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        Cache::forget('bouncer:blocklist');
        Cache::forget('bouncer:blocked_ips_table_exists');

        $this->assertFalse($this->service->isBlocked('192.168.1.100'));
    }

    public function test_is_blocked_works_with_null_expiry(): void
    {
        DB::table('blocked_ips')->insert([
            'ip_address' => '192.168.1.100',
            'reason' => 'permanent',
            'status' => BlocklistService::STATUS_APPROVED,
            'blocked_at' => now(),
            'expires_at' => null, // Permanent block
        ]);

        Cache::forget('bouncer:blocklist');
        Cache::forget('bouncer:blocked_ips_table_exists');

        $this->assertTrue($this->service->isBlocked('192.168.1.100'));
    }

    // =========================================================================
    // Sync From Honeypot Tests
    // =========================================================================

    public function test_sync_from_honeypot_adds_critical_hits(): void
    {
        // Insert honeypot critical hits
        DB::table('honeypot_hits')->insert([
            ['ip_address' => '10.0.0.1', 'path' => '/admin', 'severity' => 'critical', 'created_at' => now()],
            ['ip_address' => '10.0.0.2', 'path' => '/wp-admin', 'severity' => 'critical', 'created_at' => now()],
        ]);

        $count = $this->service->syncFromHoneypot();

        $this->assertEquals(2, $count);
        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '10.0.0.1',
            'reason' => 'honeypot_critical',
            'status' => BlocklistService::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '10.0.0.2',
            'reason' => 'honeypot_critical',
            'status' => BlocklistService::STATUS_PENDING,
        ]);
    }

    public function test_sync_from_honeypot_ignores_non_critical_hits(): void
    {
        DB::table('honeypot_hits')->insert([
            ['ip_address' => '10.0.0.1', 'path' => '/robots.txt', 'severity' => 'low', 'created_at' => now()],
            ['ip_address' => '10.0.0.2', 'path' => '/favicon.ico', 'severity' => 'medium', 'created_at' => now()],
        ]);

        $count = $this->service->syncFromHoneypot();

        $this->assertEquals(0, $count);
        $this->assertDatabaseCount('blocked_ips', 0);
    }

    public function test_sync_from_honeypot_ignores_old_hits(): void
    {
        DB::table('honeypot_hits')->insert([
            'ip_address' => '10.0.0.1',
            'path' => '/admin',
            'severity' => 'critical',
            'created_at' => now()->subDays(2), // Older than 24 hours
        ]);

        $count = $this->service->syncFromHoneypot();

        $this->assertEquals(0, $count);
        $this->assertDatabaseCount('blocked_ips', 0);
    }

    public function test_sync_from_honeypot_skips_already_blocked_ips(): void
    {
        // Already blocked IP
        DB::table('blocked_ips')->insert([
            'ip_address' => '10.0.0.1',
            'reason' => 'manual',
            'status' => BlocklistService::STATUS_APPROVED,
            'blocked_at' => now(),
        ]);

        // Critical hit from same IP
        DB::table('honeypot_hits')->insert([
            'ip_address' => '10.0.0.1',
            'path' => '/admin',
            'severity' => 'critical',
            'created_at' => now(),
        ]);

        $count = $this->service->syncFromHoneypot();

        $this->assertEquals(0, $count);
        $this->assertDatabaseCount('blocked_ips', 1);
    }

    public function test_sync_from_honeypot_deduplicates_ips(): void
    {
        // Multiple hits from same IP
        DB::table('honeypot_hits')->insert([
            ['ip_address' => '10.0.0.1', 'path' => '/admin', 'severity' => 'critical', 'created_at' => now()],
            ['ip_address' => '10.0.0.1', 'path' => '/wp-admin', 'severity' => 'critical', 'created_at' => now()],
            ['ip_address' => '10.0.0.1', 'path' => '/phpmyadmin', 'severity' => 'critical', 'created_at' => now()],
        ]);

        $count = $this->service->syncFromHoneypot();

        $this->assertEquals(1, $count);
        $this->assertDatabaseCount('blocked_ips', 1);
    }

    // =========================================================================
    // Pagination Tests
    // =========================================================================

    public function test_get_blocklist_paginated_returns_paginator(): void
    {
        // Insert multiple blocked IPs
        for ($i = 1; $i <= 10; $i++) {
            DB::table('blocked_ips')->insert([
                'ip_address' => "192.168.1.{$i}",
                'reason' => 'test',
                'status' => BlocklistService::STATUS_APPROVED,
                'blocked_at' => now(),
            ]);
        }

        $result = $this->service->getBlocklistPaginated(5);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(10, $result->total());
        $this->assertEquals(5, $result->perPage());
        $this->assertCount(5, $result->items());
    }

    public function test_get_blocklist_paginated_filters_by_status(): void
    {
        DB::table('blocked_ips')->insert([
            ['ip_address' => '192.168.1.1', 'reason' => 'test', 'status' => BlocklistService::STATUS_APPROVED, 'blocked_at' => now()],
            ['ip_address' => '192.168.1.2', 'reason' => 'test', 'status' => BlocklistService::STATUS_PENDING, 'blocked_at' => now()],
            ['ip_address' => '192.168.1.3', 'reason' => 'test', 'status' => BlocklistService::STATUS_REJECTED, 'blocked_at' => now()],
        ]);

        $approved = $this->service->getBlocklistPaginated(10, BlocklistService::STATUS_APPROVED);
        $pending = $this->service->getBlocklistPaginated(10, BlocklistService::STATUS_PENDING);

        $this->assertEquals(1, $approved->total());
        $this->assertEquals(1, $pending->total());
    }

    public function test_get_blocklist_paginated_orders_by_blocked_at_desc(): void
    {
        DB::table('blocked_ips')->insert([
            ['ip_address' => '192.168.1.1', 'reason' => 'test', 'status' => BlocklistService::STATUS_APPROVED, 'blocked_at' => now()->subHours(2)],
            ['ip_address' => '192.168.1.2', 'reason' => 'test', 'status' => BlocklistService::STATUS_APPROVED, 'blocked_at' => now()],
            ['ip_address' => '192.168.1.3', 'reason' => 'test', 'status' => BlocklistService::STATUS_APPROVED, 'blocked_at' => now()->subHour()],
        ]);

        $result = $this->service->getBlocklistPaginated(10);
        $items = collect($result->items());

        // Should be ordered most recent first
        $this->assertEquals('192.168.1.2', $items->first()->ip_address);
        $this->assertEquals('192.168.1.1', $items->last()->ip_address);
    }

    public function test_get_pending_returns_array_when_per_page_is_null(): void
    {
        DB::table('blocked_ips')->insert([
            ['ip_address' => '192.168.1.1', 'reason' => 'test', 'status' => BlocklistService::STATUS_PENDING, 'blocked_at' => now()],
            ['ip_address' => '192.168.1.2', 'reason' => 'test', 'status' => BlocklistService::STATUS_PENDING, 'blocked_at' => now()],
        ]);

        $result = $this->service->getPending(null);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_get_pending_returns_paginator_when_per_page_provided(): void
    {
        DB::table('blocked_ips')->insert([
            ['ip_address' => '192.168.1.1', 'reason' => 'test', 'status' => BlocklistService::STATUS_PENDING, 'blocked_at' => now()],
            ['ip_address' => '192.168.1.2', 'reason' => 'test', 'status' => BlocklistService::STATUS_PENDING, 'blocked_at' => now()],
        ]);

        $result = $this->service->getPending(1);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(2, $result->total());
        $this->assertEquals(1, $result->perPage());
    }

    // =========================================================================
    // Approval/Rejection Tests
    // =========================================================================

    public function test_approve_changes_pending_to_approved(): void
    {
        DB::table('blocked_ips')->insert([
            'ip_address' => '192.168.1.100',
            'reason' => 'test',
            'status' => BlocklistService::STATUS_PENDING,
            'blocked_at' => now(),
        ]);

        $result = $this->service->approve('192.168.1.100');

        $this->assertTrue($result);
        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '192.168.1.100',
            'status' => BlocklistService::STATUS_APPROVED,
        ]);
    }

    public function test_approve_returns_false_for_non_pending_entry(): void
    {
        DB::table('blocked_ips')->insert([
            'ip_address' => '192.168.1.100',
            'reason' => 'test',
            'status' => BlocklistService::STATUS_APPROVED, // Already approved
            'blocked_at' => now(),
        ]);

        $result = $this->service->approve('192.168.1.100');

        $this->assertFalse($result);
    }

    public function test_approve_returns_false_for_non_existent_entry(): void
    {
        $result = $this->service->approve('192.168.1.200');

        $this->assertFalse($result);
    }

    public function test_approve_clears_cache(): void
    {
        DB::table('blocked_ips')->insert([
            'ip_address' => '192.168.1.100',
            'reason' => 'test',
            'status' => BlocklistService::STATUS_PENDING,
            'blocked_at' => now(),
        ]);

        Cache::shouldReceive('forget')
            ->once()
            ->with('bouncer:blocklist');

        $this->service->approve('192.168.1.100');
    }

    public function test_reject_changes_pending_to_rejected(): void
    {
        DB::table('blocked_ips')->insert([
            'ip_address' => '192.168.1.100',
            'reason' => 'test',
            'status' => BlocklistService::STATUS_PENDING,
            'blocked_at' => now(),
        ]);

        $result = $this->service->reject('192.168.1.100');

        $this->assertTrue($result);
        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '192.168.1.100',
            'status' => BlocklistService::STATUS_REJECTED,
        ]);
    }

    public function test_reject_returns_false_for_non_pending_entry(): void
    {
        DB::table('blocked_ips')->insert([
            'ip_address' => '192.168.1.100',
            'reason' => 'test',
            'status' => BlocklistService::STATUS_APPROVED, // Not pending
            'blocked_at' => now(),
        ]);

        $result = $this->service->reject('192.168.1.100');

        $this->assertFalse($result);
    }

    // =========================================================================
    // Stats Tests
    // =========================================================================

    public function test_get_stats_returns_complete_statistics(): void
    {
        // Insert test data - each row must have same columns
        DB::table('blocked_ips')->insert([
            ['ip_address' => '192.168.1.1', 'reason' => 'manual', 'status' => BlocklistService::STATUS_APPROVED, 'blocked_at' => now(), 'expires_at' => now()->addDay()],
            ['ip_address' => '192.168.1.2', 'reason' => 'manual', 'status' => BlocklistService::STATUS_APPROVED, 'blocked_at' => now(), 'expires_at' => now()->subDay()], // Expired
            ['ip_address' => '192.168.1.3', 'reason' => 'honeypot', 'status' => BlocklistService::STATUS_PENDING, 'blocked_at' => now(), 'expires_at' => null],
            ['ip_address' => '192.168.1.4', 'reason' => 'honeypot', 'status' => BlocklistService::STATUS_REJECTED, 'blocked_at' => now(), 'expires_at' => null],
        ]);

        Cache::forget('bouncer:blocked_ips_table_exists');
        $stats = $this->service->getStats();

        $this->assertEquals(4, $stats['total_blocked']);
        $this->assertEquals(1, $stats['active_blocked']); // Only 1 approved and not expired
        $this->assertEquals(1, $stats['pending_review']);
        $this->assertEquals(['manual' => 2, 'honeypot' => 2], $stats['by_reason']);
        $this->assertEquals([
            BlocklistService::STATUS_APPROVED => 2,
            BlocklistService::STATUS_PENDING => 1,
            BlocklistService::STATUS_REJECTED => 1,
        ], $stats['by_status']);
    }

    public function test_get_stats_returns_zeros_when_table_is_empty(): void
    {
        Cache::forget('bouncer:blocked_ips_table_exists');
        $stats = $this->service->getStats();

        $this->assertEquals(0, $stats['total_blocked']);
        $this->assertEquals(0, $stats['active_blocked']);
        $this->assertEquals(0, $stats['pending_review']);
        $this->assertEmpty($stats['by_reason']);
        $this->assertEmpty($stats['by_status']);
    }

    // =========================================================================
    // Cache Tests
    // =========================================================================

    public function test_clear_cache_removes_cached_blocklist(): void
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('bouncer:blocklist');

        $this->service->clearCache();
    }

    public function test_get_blocklist_uses_cache(): void
    {
        $cachedData = ['192.168.1.1' => 'test_reason'];

        Cache::shouldReceive('remember')
            ->once()
            ->with('bouncer:blocklist', 300, \Mockery::type('Closure'))
            ->andReturn($cachedData);

        $result = $this->service->getBlocklist();

        $this->assertEquals($cachedData, $result);
    }

    // =========================================================================
    // Status Constants Tests
    // =========================================================================

    public function test_status_constants_are_defined(): void
    {
        $this->assertEquals('pending', BlocklistService::STATUS_PENDING);
        $this->assertEquals('approved', BlocklistService::STATUS_APPROVED);
        $this->assertEquals('rejected', BlocklistService::STATUS_REJECTED);
    }
}
