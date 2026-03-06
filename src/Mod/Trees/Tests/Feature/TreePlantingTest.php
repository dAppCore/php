<?php

declare(strict_types=1);

use Core\Mod\Trees\Models\TreePlanting;
use Core\Mod\Trees\Models\TreePlantingStats;
use Core\Mod\Trees\Models\TreeReserve;
use Core\Tenant\Models\User;
use Core\Tenant\Models\Workspace;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create();

    // Ensure tree reserve has trees available
    TreeReserve::replenish(100);
});

describe('TreePlanting Model', function () {
    describe('scopes', function () {
        it('filters by agent referral source with forAgent scope', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);
            TreePlanting::create([
                'provider' => null,
                'source' => TreePlanting::SOURCE_SUBSCRIPTION,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            $agentReferrals = TreePlanting::forAgent()->get();

            expect($agentReferrals)->toHaveCount(1);
            expect($agentReferrals->first()->source)->toBe(TreePlanting::SOURCE_AGENT_REFERRAL);
        });

        it('filters by provider with byProvider scope', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);
            TreePlanting::create([
                'provider' => 'openai',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            $anthropicPlantings = TreePlanting::byProvider('anthropic')->get();

            expect($anthropicPlantings)->toHaveCount(1);
            expect($anthropicPlantings->first()->provider)->toBe('anthropic');
        });

        it('filters by queued status with queued scope', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_QUEUED,
            ]);
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            $queuedPlantings = TreePlanting::queued()->get();

            expect($queuedPlantings)->toHaveCount(1);
            expect($queuedPlantings->first()->status)->toBe(TreePlanting::STATUS_QUEUED);
        });

        it('filters by pending status with pending scope', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_PENDING,
            ]);
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            $pendingPlantings = TreePlanting::pending()->get();

            expect($pendingPlantings)->toHaveCount(1);
            expect($pendingPlantings->first()->status)->toBe(TreePlanting::STATUS_PENDING);
        });

        it('filters by confirmed status with confirmed scope', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_PLANTED,
            ]);

            $confirmedPlantings = TreePlanting::confirmed()->get();

            expect($confirmedPlantings)->toHaveCount(1);
            expect($confirmedPlantings->first()->status)->toBe(TreePlanting::STATUS_CONFIRMED);
        });

        it('filters by planted status with planted scope', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_PLANTED,
                'tftf_reference' => 'TFTF-20260101-ABC123',
            ]);
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            $plantedPlantings = TreePlanting::planted()->get();

            expect($plantedPlantings)->toHaveCount(1);
            expect($plantedPlantings->first()->status)->toBe(TreePlanting::STATUS_PLANTED);
        });

        it('filters today plantings with today scope', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
                'created_at' => now(),
            ]);
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
                'created_at' => now()->subDays(2),
            ]);

            $todayPlantings = TreePlanting::today()->get();

            expect($todayPlantings)->toHaveCount(1);
        });

        it('filters this month plantings with thisMonth scope', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
                'created_at' => now(),
            ]);
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
                'created_at' => now()->subMonths(2),
            ]);

            $thisMonthPlantings = TreePlanting::thisMonth()->get();

            expect($thisMonthPlantings)->toHaveCount(1);
        });
    });

    describe('helper methods', function () {
        it('validates providers correctly', function () {
            expect(TreePlanting::isValidProvider('anthropic'))->toBeTrue();
            expect(TreePlanting::isValidProvider('openai'))->toBeTrue();
            expect(TreePlanting::isValidProvider('google'))->toBeTrue();
            expect(TreePlanting::isValidProvider('meta'))->toBeTrue();
            expect(TreePlanting::isValidProvider('mistral'))->toBeTrue();
            expect(TreePlanting::isValidProvider('local'))->toBeTrue();
            expect(TreePlanting::isValidProvider('unknown'))->toBeTrue();
            expect(TreePlanting::isValidProvider('invalid'))->toBeFalse();
        });

        it('correctly identifies agent referrals', function () {
            $agentPlanting = TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);
            $subscriptionPlanting = TreePlanting::create([
                'provider' => null,
                'source' => TreePlanting::SOURCE_SUBSCRIPTION,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            expect($agentPlanting->isAgentReferral())->toBeTrue();
            expect($subscriptionPlanting->isAgentReferral())->toBeFalse();
        });

        it('counts trees planted today from agents', function () {
            // Create 3 trees today from agents
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 2,
                'status' => TreePlanting::STATUS_CONFIRMED,
                'created_at' => now(),
            ]);
            TreePlanting::create([
                'provider' => 'openai',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
                'created_at' => now(),
            ]);
            // This one is from yesterday
            TreePlanting::create([
                'provider' => 'google',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 5,
                'status' => TreePlanting::STATUS_CONFIRMED,
                'created_at' => now()->subDay(),
            ]);
            // This one is from subscription (not agent)
            TreePlanting::create([
                'provider' => null,
                'source' => TreePlanting::SOURCE_SUBSCRIPTION,
                'trees' => 10,
                'status' => TreePlanting::STATUS_CONFIRMED,
                'created_at' => now(),
            ]);

            expect(TreePlanting::treesPlantedTodayFromAgents())->toBe(3);
        });
    });

    describe('markConfirmed', function () {
        it('decrements tree reserve when confirming', function () {
            $initialReserve = TreeReserve::current();

            $planting = TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 5,
                'status' => TreePlanting::STATUS_PENDING,
            ]);

            $planting->markConfirmed();

            expect($planting->fresh()->status)->toBe(TreePlanting::STATUS_CONFIRMED);
            expect(TreeReserve::current())->toBe($initialReserve - 5);
        });

        it('queues tree when reserve is depleted', function () {
            // Deplete the reserve
            $reserve = TreeReserve::instance();
            $reserve->update(['reserve' => 0]);

            $planting = TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_PENDING,
            ]);

            $planting->markConfirmed();

            expect($planting->fresh()->status)->toBe(TreePlanting::STATUS_QUEUED);
        });

        it('updates tree planting stats on confirmation', function () {
            $planting = TreePlanting::create([
                'provider' => 'anthropic',
                'model' => 'claude-opus',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 3,
                'status' => TreePlanting::STATUS_PENDING,
            ]);

            $planting->markConfirmed();

            $stats = TreePlantingStats::where('provider', 'anthropic')
                ->where('model', 'claude-opus')
                ->whereDate('date', today())
                ->first();

            expect($stats)->not->toBeNull();
            expect($stats->total_trees)->toBe(3);
            expect($stats->total_signups)->toBe(1);
        });
    });

    describe('markPlanted', function () {
        it('sets status to planted with batch reference', function () {
            $planting = TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 1,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            $planting->markPlanted('TFTF-20260102-ABC123');

            expect($planting->fresh()->status)->toBe(TreePlanting::STATUS_PLANTED);
            expect($planting->fresh()->tftf_reference)->toBe('TFTF-20260102-ABC123');
        });
    });
});
