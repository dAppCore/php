<?php

declare(strict_types=1);

use Core\Mod\Trees\Listeners\PlantTreeForAgentReferral;
use Core\Mod\Trees\Models\TreePlanting;
use Core\Mod\Trees\Models\TreeReserve;
use Core\Tenant\Models\User;
use Illuminate\Auth\Events\Registered;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Ensure tree reserve has trees available
    TreeReserve::replenish(100);
});

describe('Signup with Agent Referral', function () {
    it('creates TreePlanting when user signs up via referral', function () {
        // First, make a request to set up the session with referral data
        $this->get('/ref/anthropic/claude-opus');

        // Create a user
        $user = User::factory()->create();

        // Fire the event within a request context
        $this->app->make('request')->setLaravelSession(
            $this->app->make('session.store')
        );

        $listener = new PlantTreeForAgentReferral;
        $listener->handle(new Registered($user));

        // Check that a TreePlanting was created
        $planting = TreePlanting::where('user_id', $user->id)->first();

        expect($planting)->not->toBeNull();
        expect($planting->provider)->toBe('anthropic');
        expect($planting->model)->toBe('claude-opus');
        expect($planting->source)->toBe(TreePlanting::SOURCE_AGENT_REFERRAL);
        expect($planting->trees)->toBe(1);
    });

    it('does not create TreePlanting without referral', function () {
        // Make a request without referral to set up session
        $this->get('/');

        // Set up request with session
        $this->app->make('request')->setLaravelSession(
            $this->app->make('session.store')
        );

        $user = User::factory()->create();

        $listener = new PlantTreeForAgentReferral;
        $listener->handle(new Registered($user));

        $planting = TreePlanting::where('user_id', $user->id)->first();

        expect($planting)->toBeNull();
    });

    it('confirms first tree of day immediately', function () {
        // Set up referral via route
        $this->get('/ref/anthropic');

        $this->app->make('request')->setLaravelSession(
            $this->app->make('session.store')
        );

        $user = User::factory()->create();

        $listener = new PlantTreeForAgentReferral;
        $listener->handle(new Registered($user));

        $planting = TreePlanting::where('user_id', $user->id)->first();

        expect($planting->status)->toBe(TreePlanting::STATUS_CONFIRMED);
    });

    it('queues tree when daily limit reached', function () {
        // Create a tree planted today to hit the limit
        TreePlanting::create([
            'provider' => 'openai',
            'source' => TreePlanting::SOURCE_AGENT_REFERRAL,
            'trees' => 1,
            'status' => TreePlanting::STATUS_CONFIRMED,
            'created_at' => now(),
        ]);

        // Set up referral via route
        $this->get('/ref/anthropic');

        $this->app->make('request')->setLaravelSession(
            $this->app->make('session.store')
        );

        $user = User::factory()->create();

        $listener = new PlantTreeForAgentReferral;
        $listener->handle(new Registered($user));

        $planting = TreePlanting::where('user_id', $user->id)->first();

        expect($planting->status)->toBe(TreePlanting::STATUS_QUEUED);
    });

    it('clears referral after creating tree', function () {
        // Set up referral via route
        $this->get('/ref/anthropic');

        $this->app->make('request')->setLaravelSession(
            $this->app->make('session.store')
        );

        $user = User::factory()->create();

        $listener = new PlantTreeForAgentReferral;
        $listener->handle(new Registered($user));

        // Referral should be cleared
        expect(session('agent_referral'))->toBeNull();
    });

    it('stores metadata in tree planting', function () {
        // Set up referral via route
        $this->get('/ref/anthropic/claude-opus');

        $this->app->make('request')->setLaravelSession(
            $this->app->make('session.store')
        );

        $user = User::factory()->create();

        $listener = new PlantTreeForAgentReferral;
        $listener->handle(new Registered($user));

        $planting = TreePlanting::where('user_id', $user->id)->first();

        expect($planting->metadata)->toBeArray();
        expect($planting->metadata['referred_at'])->not->toBeNull();
    });
});
