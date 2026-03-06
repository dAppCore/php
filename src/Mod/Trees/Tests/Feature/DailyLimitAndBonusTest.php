<?php

declare(strict_types=1);

use Core\Mod\Trees\Models\TreePlanting;
use Core\Mod\Trees\Models\TreeReserve;
use Core\Tenant\Models\AgentReferralBonus;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Ensure tree reserve has trees available
    TreeReserve::replenish(100);
});

describe('Daily Limit Enforcement', function () {
    it('allows first tree of the day immediately', function () {
        $treesToday = TreePlanting::treesPlantedTodayFromAgents();

        expect($treesToday)->toBe(0);
    });

    it('counts only today agent referrals towards daily limit', function () {
        // Yesterday's planting (should not count)
        TreePlanting::create([
            'provider' => 'anthropic',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_CONFIRMED,
            'created_at' => now()->subDay(),
        ]);

        // Today's subscription planting (should not count - wrong source)
        TreePlanting::create([
            'provider' => null,
            'source' => TreePlanting::SOURCE_SUBSCRIPTION,
            'trees' => 1,
            'status' => TreePlanting::STATUS_CONFIRMED,
            'created_at' => now(),
        ]);

        // Today's agent planting (should count)
        TreePlanting::create([
            'provider' => 'openai',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_CONFIRMED,
            'created_at' => now(),
        ]);

        $treesToday = TreePlanting::treesPlantedTodayFromAgents();

        expect($treesToday)->toBe(1);
    });

    it('includes pending status in daily count', function () {
        TreePlanting::create([
            'provider' => 'anthropic',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_PENDING,
            'created_at' => now(),
        ]);

        $treesToday = TreePlanting::treesPlantedTodayFromAgents();

        expect($treesToday)->toBe(1);
    });

    it('excludes queued status from daily count', function () {
        TreePlanting::create([
            'provider' => 'anthropic',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_QUEUED,
            'created_at' => now(),
        ]);

        $treesToday = TreePlanting::treesPlantedTodayFromAgents();

        expect($treesToday)->toBe(0);
    });
});

describe('Conversion Bonus Logic', function () {
    it('grants guaranteed referral bonus', function () {
        $bonus = AgentReferralBonus::grantGuaranteedReferral('anthropic', 'claude-opus');

        expect($bonus->next_referral_guaranteed)->toBeTrue();
        expect($bonus->provider)->toBe('anthropic');
        expect($bonus->model)->toBe('claude-opus');
        expect($bonus->last_conversion_at)->not->toBeNull();
    });

    it('increments total conversions on grant', function () {
        $bonus1 = AgentReferralBonus::grantGuaranteedReferral('anthropic', 'claude-opus');
        expect($bonus1->total_conversions)->toBe(1);

        $bonus2 = AgentReferralBonus::grantGuaranteedReferral('anthropic', 'claude-opus');
        expect($bonus2->total_conversions)->toBe(2);
    });

    it('checks for guaranteed referral correctly', function () {
        expect(AgentReferralBonus::hasGuaranteedReferral('anthropic', 'claude-opus'))->toBeFalse();

        AgentReferralBonus::grantGuaranteedReferral('anthropic', 'claude-opus');

        expect(AgentReferralBonus::hasGuaranteedReferral('anthropic', 'claude-opus'))->toBeTrue();
        expect(AgentReferralBonus::hasGuaranteedReferral('anthropic', 'claude-sonnet'))->toBeFalse();
        expect(AgentReferralBonus::hasGuaranteedReferral('openai', null))->toBeFalse();
    });

    it('consumes guaranteed referral bonus', function () {
        AgentReferralBonus::grantGuaranteedReferral('anthropic', 'claude-opus');

        expect(AgentReferralBonus::hasGuaranteedReferral('anthropic', 'claude-opus'))->toBeTrue();

        $consumed = AgentReferralBonus::consumeGuaranteedReferral('anthropic', 'claude-opus');

        expect($consumed)->toBeTrue();
        expect(AgentReferralBonus::hasGuaranteedReferral('anthropic', 'claude-opus'))->toBeFalse();
    });

    it('returns false when consuming non-existent bonus', function () {
        $consumed = AgentReferralBonus::consumeGuaranteedReferral('anthropic', 'claude-opus');

        expect($consumed)->toBeFalse();
    });

    it('handles null model for bonus', function () {
        AgentReferralBonus::grantGuaranteedReferral('unknown', null);

        expect(AgentReferralBonus::hasGuaranteedReferral('unknown', null))->toBeTrue();

        $consumed = AgentReferralBonus::consumeGuaranteedReferral('unknown', null);

        expect($consumed)->toBeTrue();
        expect(AgentReferralBonus::hasGuaranteedReferral('unknown', null))->toBeFalse();
    });

    it('keeps bonus separate per provider and model', function () {
        AgentReferralBonus::grantGuaranteedReferral('anthropic', 'claude-opus');
        AgentReferralBonus::grantGuaranteedReferral('anthropic', 'claude-sonnet');
        AgentReferralBonus::grantGuaranteedReferral('openai', 'gpt-4');

        // Consume only claude-opus
        AgentReferralBonus::consumeGuaranteedReferral('anthropic', 'claude-opus');

        // Others should still be available
        expect(AgentReferralBonus::hasGuaranteedReferral('anthropic', 'claude-opus'))->toBeFalse();
        expect(AgentReferralBonus::hasGuaranteedReferral('anthropic', 'claude-sonnet'))->toBeTrue();
        expect(AgentReferralBonus::hasGuaranteedReferral('openai', 'gpt-4'))->toBeTrue();
    });
});
