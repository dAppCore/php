<?php

declare(strict_types=1);

use Core\Mod\Commerce\Models\Subscription;
use Core\Mod\Trees\Models\TreePlanting;
use Core\Mod\Trees\Models\TreeReserve;
use Core\Tenant\Models\Package;
use Core\Tenant\Models\User;
use Core\Tenant\Models\Workspace;
use Core\Tenant\Models\WorkspacePackage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    TreeReserve::replenish(100);

    // Create a test user and workspace
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create();
    $this->workspace->users()->attach($this->user->id, [
        'role' => 'owner',
        'is_default' => true,
    ]);

    // Create a package
    $this->package = Package::create([
        'code' => 'creator',
        'name' => 'Creator',
        'description' => 'Creator package',
        'is_stackable' => false,
        'is_base_package' => true,
        'is_active' => true,
        'is_public' => true,
        'sort_order' => 1,
    ]);

    // Create workspace package
    $this->workspacePackage = WorkspacePackage::create([
        'workspace_id' => $this->workspace->id,
        'package_id' => $this->package->id,
        'assigned_at' => now(),
        'is_active' => true,
    ]);
});

describe('Subscriber Monthly Trees Command', function () {
    it('plants 1 tree per active subscription', function () {
        // Create an active subscription
        Subscription::create([
            'workspace_id' => $this->workspace->id,
            'workspace_package_id' => $this->workspacePackage->id,
            'status' => 'active',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->artisan('trees:subscriber-monthly')
            ->assertSuccessful();

        $planting = TreePlanting::where('workspace_id', $this->workspace->id)
            ->where('source', TreePlanting::SOURCE_SUBSCRIPTION)
            ->first();

        expect($planting)->not->toBeNull();
        expect($planting->trees)->toBe(1);
        expect($planting->status)->toBe(TreePlanting::STATUS_CONFIRMED);
    });

    it('plants 2 trees for enterprise subscriptions', function () {
        // Create enterprise package
        $enterprisePackage = Package::create([
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'description' => 'Enterprise package',
            'is_stackable' => false,
            'is_base_package' => true,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 2,
        ]);

        $enterpriseWorkspacePackage = WorkspacePackage::create([
            'workspace_id' => $this->workspace->id,
            'package_id' => $enterprisePackage->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        Subscription::create([
            'workspace_id' => $this->workspace->id,
            'workspace_package_id' => $enterpriseWorkspacePackage->id,
            'status' => 'active',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->artisan('trees:subscriber-monthly')
            ->assertSuccessful();

        $planting = TreePlanting::where('workspace_id', $this->workspace->id)
            ->where('source', TreePlanting::SOURCE_SUBSCRIPTION)
            ->first();

        expect($planting)->not->toBeNull();
        expect($planting->trees)->toBe(2);
    });

    it('is idempotent - does not duplicate trees in same month', function () {
        Subscription::create([
            'workspace_id' => $this->workspace->id,
            'workspace_package_id' => $this->workspacePackage->id,
            'status' => 'active',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
        ]);

        // Run command twice
        $this->artisan('trees:subscriber-monthly')->assertSuccessful();
        $this->artisan('trees:subscriber-monthly')->assertSuccessful();

        $plantings = TreePlanting::where('workspace_id', $this->workspace->id)
            ->where('source', TreePlanting::SOURCE_SUBSCRIPTION)
            ->count();

        expect($plantings)->toBe(1);
    });

    it('skips inactive subscriptions', function () {
        Subscription::create([
            'workspace_id' => $this->workspace->id,
            'workspace_package_id' => $this->workspacePackage->id,
            'status' => 'cancelled',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->artisan('trees:subscriber-monthly')->assertSuccessful();

        $planting = TreePlanting::where('workspace_id', $this->workspace->id)
            ->where('source', TreePlanting::SOURCE_SUBSCRIPTION)
            ->first();

        expect($planting)->toBeNull();
    });

    it('supports dry-run mode', function () {
        Subscription::create([
            'workspace_id' => $this->workspace->id,
            'workspace_package_id' => $this->workspacePackage->id,
            'status' => 'active',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->artisan('trees:subscriber-monthly --dry-run')
            ->assertSuccessful();

        $planting = TreePlanting::where('workspace_id', $this->workspace->id)
            ->where('source', TreePlanting::SOURCE_SUBSCRIPTION)
            ->first();

        expect($planting)->toBeNull();
    });
});
