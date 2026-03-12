<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Actions\ScheduledAction;
use Core\Console\Commands\ScheduleSyncCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class ScheduleSyncCommandTest extends TestCase
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

        // Point scanner at test fixtures only
        $app['config']->set('core.scheduled_action_paths', [
            __DIR__.'/../Fixtures/Mod/Scheduled',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->registerCommand(
            $this->app->make(ScheduleSyncCommand::class)
        );
    }

    public function test_sync_inserts_new_scheduled_actions(): void
    {
        $this->artisan('schedule:sync')
            ->assertSuccessful();

        $this->assertDatabaseHas('scheduled_actions', [
            'action_class' => 'Core\\Tests\\Fixtures\\Mod\\Scheduled\\Actions\\EveryMinuteAction',
            'frequency' => 'everyMinute',
            'is_enabled' => true,
        ]);

        $this->assertDatabaseHas('scheduled_actions', [
            'action_class' => 'Core\\Tests\\Fixtures\\Mod\\Scheduled\\Actions\\DailyAction',
            'frequency' => 'dailyAt:09:00',
            'timezone' => 'Europe/London',
        ]);
    }

    public function test_sync_disables_removed_actions(): void
    {
        // Pre-populate with an action that no longer exists
        ScheduledAction::create([
            'action_class' => 'App\\Actions\\RemovedAction',
            'frequency' => 'hourly',
            'is_enabled' => true,
        ]);

        $this->artisan('schedule:sync')
            ->assertSuccessful();

        $this->assertDatabaseHas('scheduled_actions', [
            'action_class' => 'App\\Actions\\RemovedAction',
            'is_enabled' => false,
        ]);
    }

    public function test_sync_preserves_manually_edited_frequency(): void
    {
        // Pre-populate with a manually edited action
        ScheduledAction::create([
            'action_class' => 'Core\\Tests\\Fixtures\\Mod\\Scheduled\\Actions\\EveryMinuteAction',
            'frequency' => 'hourly', // Manually changed from everyMinute
            'is_enabled' => true,
        ]);

        $this->artisan('schedule:sync')
            ->assertSuccessful();

        // Should preserve the manual edit
        $this->assertDatabaseHas('scheduled_actions', [
            'action_class' => 'Core\\Tests\\Fixtures\\Mod\\Scheduled\\Actions\\EveryMinuteAction',
            'frequency' => 'hourly',
        ]);
    }

    public function test_sync_is_idempotent(): void
    {
        $this->artisan('schedule:sync')->assertSuccessful();
        $this->artisan('schedule:sync')->assertSuccessful();

        $count = ScheduledAction::where(
            'action_class',
            'Core\\Tests\\Fixtures\\Mod\\Scheduled\\Actions\\EveryMinuteAction'
        )->count();

        $this->assertSame(1, $count);
    }
}
