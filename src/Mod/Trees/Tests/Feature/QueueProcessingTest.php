<?php

declare(strict_types=1);

use Core\Mod\Trees\Models\TreePlanting;
use Core\Mod\Trees\Models\TreeReserve;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    TreeReserve::replenish(100);
});

describe('Queue Processing Command', function () {
    it('processes oldest queued tree first', function () {
        // Create three queued trees with different timestamps
        $oldest = TreePlanting::create([
            'provider' => 'anthropic',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_QUEUED,
            'created_at' => now()->subDays(3),
        ]);
        TreePlanting::create([
            'provider' => 'openai',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_QUEUED,
            'created_at' => now()->subDays(2),
        ]);
        TreePlanting::create([
            'provider' => 'google',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_QUEUED,
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('trees:process-queue')->assertSuccessful();

        // Oldest should now be confirmed
        expect($oldest->fresh()->status)->toBe(TreePlanting::STATUS_CONFIRMED);

        // Others should still be queued
        $remaining = TreePlanting::queued()->count();
        expect($remaining)->toBe(2);
    });

    it('processes only one tree per run', function () {
        TreePlanting::create([
            'provider' => 'anthropic',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_QUEUED,
            'created_at' => now()->subDays(2),
        ]);
        TreePlanting::create([
            'provider' => 'openai',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_QUEUED,
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('trees:process-queue')->assertSuccessful();

        $confirmed = TreePlanting::confirmed()->count();
        $queued = TreePlanting::queued()->count();

        expect($confirmed)->toBe(1);
        expect($queued)->toBe(1);
    });

    it('handles empty queue gracefully', function () {
        $this->artisan('trees:process-queue')
            ->assertSuccessful()
            ->expectsOutputToContain('No queued trees');
    });

    it('supports dry-run mode', function () {
        $queued = TreePlanting::create([
            'provider' => 'anthropic',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_QUEUED,
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('trees:process-queue --dry-run')
            ->assertSuccessful();

        // Should still be queued
        expect($queued->fresh()->status)->toBe(TreePlanting::STATUS_QUEUED);
    });

    it('decrements reserve when processing queue', function () {
        $initialReserve = TreeReserve::current();

        TreePlanting::create([
            'provider' => 'anthropic',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 5,
            'status' => TreePlanting::STATUS_QUEUED,
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('trees:process-queue')->assertSuccessful();

        expect(TreeReserve::current())->toBe($initialReserve - 5);
    });

    it('uses oldestQueued helper method', function () {
        TreePlanting::create([
            'provider' => 'anthropic',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_QUEUED,
            'created_at' => now()->subDays(5),
        ]);
        TreePlanting::create([
            'provider' => 'openai',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_QUEUED,
            'created_at' => now()->subDay(),
        ]);

        $oldest = TreePlanting::oldestQueued();

        expect($oldest->provider)->toBe('anthropic');
    });
});

describe('Queue Reserve Integration', function () {
    it('keeps trees queued when reserve is depleted', function () {
        // Deplete the reserve
        $reserve = TreeReserve::instance();
        $reserve->update(['reserve' => 0]);

        $queued = TreePlanting::create([
            'provider' => 'anthropic',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_QUEUED,
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('trees:process-queue')->assertSuccessful();

        // Should still be queued because reserve is empty
        expect($queued->fresh()->status)->toBe(TreePlanting::STATUS_QUEUED);
    });

    it('processes queue when reserve is replenished', function () {
        // First deplete reserve
        $reserve = TreeReserve::instance();
        $reserve->update(['reserve' => 0]);

        $queued = TreePlanting::create([
            'provider' => 'anthropic',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_QUEUED,
            'created_at' => now()->subDay(),
        ]);

        // Replenish the reserve
        TreeReserve::replenish(10);

        $this->artisan('trees:process-queue')->assertSuccessful();

        // Should now be confirmed
        expect($queued->fresh()->status)->toBe(TreePlanting::STATUS_CONFIRMED);
    });
});
