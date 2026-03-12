<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Actions\ScheduledAction;
use Core\Actions\ScheduleServiceProvider;
use Core\Console\Commands\ScheduleSyncCommand;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class ScheduledActionsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $app['config']->set('core.scheduled_action_paths', [
            __DIR__.'/../Fixtures/Mod/Scheduled',
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ScheduleServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Kernel::class)->registerCommand(
            $this->app->make(ScheduleSyncCommand::class)
        );
    }

    public function test_full_flow_scan_sync_schedule(): void
    {
        // Step 1: Sync discovers and persists
        $this->artisan('schedule:sync')->assertSuccessful();

        // Step 2: Verify rows exist
        $this->assertDatabaseHas('scheduled_actions', [
            'action_class' => 'Core\\Tests\\Fixtures\\Mod\\Scheduled\\Actions\\EveryMinuteAction',
            'is_enabled' => true,
        ]);

        // Step 3: Provider registers with scheduler
        $provider = new ScheduleServiceProvider($this->app);
        $provider->boot();
    }

    public function test_disabled_action_not_scheduled(): void
    {
        $this->artisan('schedule:sync')->assertSuccessful();

        // Disable one
        ScheduledAction::where('action_class', 'like', '%EveryMinute%')
            ->update(['is_enabled' => false]);

        $enabled = ScheduledAction::enabled()->get();
        $classes = $enabled->pluck('action_class')->toArray();

        $this->assertNotContains(
            'Core\\Tests\\Fixtures\\Mod\\Scheduled\\Actions\\EveryMinuteAction',
            $classes
        );
    }

    public function test_resync_after_disable_does_not_reenable(): void
    {
        $this->artisan('schedule:sync')->assertSuccessful();

        // Admin disables an action
        ScheduledAction::where('action_class', 'like', '%EveryMinute%')
            ->update(['is_enabled' => false]);

        // Re-sync (deploy)
        $this->artisan('schedule:sync')->assertSuccessful();

        // Should still be disabled (existing row preserved)
        $this->assertDatabaseHas('scheduled_actions', [
            'action_class' => 'Core\\Tests\\Fixtures\\Mod\\Scheduled\\Actions\\EveryMinuteAction',
            'is_enabled' => false,
        ]);
    }
}
