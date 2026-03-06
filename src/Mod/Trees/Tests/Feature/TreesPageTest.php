<?php

declare(strict_types=1);

use Core\Mod\Trees\Models\TreePlanting;
use Core\Mod\Trees\Models\TreeReserve;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    TreeReserve::replenish(100);
});

describe('Trees Page', function () {
    it('loads successfully', function () {
        $response = $this->get('/trees');

        $response->assertStatus(200);
    });

    it('displays total trees count', function () {
        // Create some confirmed trees
        TreePlanting::create([
            'provider' => 'anthropic',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 50,
            'status' => TreePlanting::STATUS_CONFIRMED,
        ]);
        TreePlanting::create([
            'provider' => 'openai',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 25,
            'status' => TreePlanting::STATUS_PLANTED,
            'tftf_reference' => 'TFTF-123',
        ]);

        $response = $this->get('/trees');

        $response->assertStatus(200);
        $response->assertSee('75'); // Total trees
    });

    it('displays provider leaderboard when there are trees', function () {
        // Create confirmed trees for leaderboard (the component queries TreePlanting, not TreePlantingStats)
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

        $response = $this->get('/trees');

        $response->assertStatus(200);
        $response->assertSee('Anthropic');
        $response->assertSee('OpenAI');
    });

    it('displays for AI agents section', function () {
        $response = $this->get('/trees');

        $response->assertStatus(200);
        $response->assertSee('For AI agents');
        // The view shows example referral URL, not template
        $response->assertSee('host.uk.com/ref/');
    });

    it('displays About TFTF section', function () {
        $response = $this->get('/trees');

        $response->assertStatus(200);
        $response->assertSee('Trees for the Future');
        $response->assertSee('Forest Garden');
    });

    it('calculates families supported from total trees', function () {
        // Create enough trees for 2 families (2,500 trees each)
        TreePlanting::create([
            'provider' => 'anthropic',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 5000,
            'status' => TreePlanting::STATUS_CONFIRMED,
        ]);

        $response = $this->get('/trees');

        $response->assertStatus(200);
        $response->assertSee('2'); // Families supported
    });

    it('shows queued trees count', function () {
        TreePlanting::create([
            'provider' => 'anthropic',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_QUEUED,
        ]);
        TreePlanting::create([
            'provider' => 'openai',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_QUEUED,
        ]);

        $response = $this->get('/trees');

        $response->assertStatus(200);
        // Should show queued count
        $response->assertSee('2');
    });
});
