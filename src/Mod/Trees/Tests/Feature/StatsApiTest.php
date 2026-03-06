<?php

declare(strict_types=1);

use Core\Mod\Trees\Models\TreePlanting;
use Core\Mod\Trees\Models\TreeReserve;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    TreeReserve::replenish(100);
});

describe('Stats API', function () {
    describe('GET /api/trees/stats', function () {
        it('returns global totals', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 100,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);
            TreePlanting::create([
                'provider' => 'openai',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 50,
                'status' => TreePlanting::STATUS_PLANTED,
                'tftf_reference' => 'TFTF-123',
            ]);

            $response = $this->getJson('/api/trees/stats');

            $response->assertStatus(200);
            $response->assertJson([
                'success' => true,
                'stats' => [
                    'total_trees' => 150,
                ],
            ]);
        });

        it('returns trees this month and year', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 25,
                'status' => TreePlanting::STATUS_CONFIRMED,
                'created_at' => now(),
            ]);

            $response = $this->getJson('/api/trees/stats');

            $response->assertStatus(200);
            $response->assertJsonPath('stats.trees_this_month', 25);
            $response->assertJsonPath('stats.trees_this_year', 25);
        });

        it('returns families supported', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 2500,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            $response = $this->getJson('/api/trees/stats');

            $response->assertStatus(200);
            $response->assertJsonPath('stats.families_supported', 1);
        });

        it('includes helpful links', function () {
            $response = $this->getJson('/api/trees/stats');

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'links' => [
                    'leaderboard',
                    'programme_info',
                    'for_agents',
                ],
            ]);
        });
    });

    describe('GET /api/trees/stats/{provider}', function () {
        it('returns provider stats', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'model' => 'claude-opus',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 100,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            $response = $this->getJson('/api/trees/stats/anthropic');

            $response->assertStatus(200);
            $response->assertJson([
                'success' => true,
                'provider' => 'anthropic',
                'display_name' => 'Anthropic',
            ]);
        });

        it('returns model breakdown for provider', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'model' => 'claude-opus',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 100,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);
            TreePlanting::create([
                'provider' => 'anthropic',
                'model' => 'claude-sonnet',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 50,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            $response = $this->getJson('/api/trees/stats/anthropic');

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'models' => [
                    '*' => ['model', 'display_name', 'trees', 'signups'],
                ],
            ]);
        });

        it('returns 404 for invalid provider', function () {
            $response = $this->getJson('/api/trees/stats/invalid-provider');

            $response->assertStatus(404);
            $response->assertJson([
                'success' => false,
            ]);
        });

        it('includes valid providers in error response', function () {
            $response = $this->getJson('/api/trees/stats/invalid-provider');

            $response->assertStatus(404);
            $response->assertJsonStructure([
                'valid_providers',
            ]);
        });
    });

    describe('GET /api/trees/stats/{provider}/{model}', function () {
        it('returns model-specific stats', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'model' => 'claude-opus',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 100,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            $response = $this->getJson('/api/trees/stats/anthropic/claude-opus');

            $response->assertStatus(200);
            $response->assertJson([
                'success' => true,
                'provider' => 'anthropic',
                'model' => 'claude-opus',
            ]);
        });

        it('includes referral URL in response', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'model' => 'claude-opus',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 100,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            $response = $this->getJson('/api/trees/stats/anthropic/claude-opus');

            $response->assertStatus(200);
            $response->assertJsonPath('referral_url', url('/ref/anthropic/claude-opus'));
        });

        it('returns 404 for model with no trees', function () {
            $response = $this->getJson('/api/trees/stats/anthropic/nonexistent-model');

            $response->assertStatus(404);
            $response->assertJsonPath('success', false);
        });
    });

    describe('GET /api/trees/leaderboard', function () {
        it('returns top providers', function () {
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 100,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);
            TreePlanting::create([
                'provider' => 'openai',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 50,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            $response = $this->getJson('/api/trees/leaderboard');

            $response->assertStatus(200);
            $response->assertJson([
                'success' => true,
            ]);
            $response->assertJsonStructure([
                'leaderboard' => [
                    '*' => ['rank', 'provider', 'display_name', 'trees', 'signups'],
                ],
            ]);
        });

        it('ranks providers by tree count', function () {
            TreePlanting::create([
                'provider' => 'openai',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 50,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);
            TreePlanting::create([
                'provider' => 'anthropic',
                'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                'trees' => 100,
                'status' => TreePlanting::STATUS_CONFIRMED,
            ]);

            $response = $this->getJson('/api/trees/leaderboard');

            $response->assertStatus(200);

            $leaderboard = $response->json('leaderboard');
            expect($leaderboard[0]['provider'])->toBe('anthropic');
            expect($leaderboard[0]['rank'])->toBe(1);
            expect($leaderboard[1]['provider'])->toBe('openai');
            expect($leaderboard[1]['rank'])->toBe(2);
        });

        it('limits to 20 results', function () {
            // Create 25 different providers
            for ($i = 0; $i < 25; $i++) {
                TreePlanting::create([
                    'provider' => "provider-{$i}",
                    'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
                    'trees' => $i + 1,
                    'status' => TreePlanting::STATUS_CONFIRMED,
                ]);
            }

            $response = $this->getJson('/api/trees/leaderboard');

            $response->assertStatus(200);
            expect($response->json('leaderboard'))->toHaveCount(20);
        });
    });
});
