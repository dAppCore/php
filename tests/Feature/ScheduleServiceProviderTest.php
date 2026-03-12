<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Actions\ScheduledAction;
use Core\Actions\ScheduleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

class ScheduleServiceProviderTest extends TestCase
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
    }

    protected function getPackageProviders($app): array
    {
        return [
            ScheduleServiceProvider::class,
        ];
    }

    public function test_provider_registers_enabled_actions_with_scheduler(): void
    {
        ScheduledAction::create([
            'action_class' => 'Core\\Tests\\Fixtures\\Mod\\Scheduled\\Actions\\EveryMinuteAction',
            'frequency' => 'everyMinute',
            'is_enabled' => true,
        ]);

        ScheduledAction::create([
            'action_class' => 'Core\\Tests\\Fixtures\\Mod\\Scheduled\\Actions\\DailyAction',
            'frequency' => 'dailyAt:09:00',
            'timezone' => 'Europe/London',
            'is_enabled' => false,
        ]);

        // Re-boot the provider to pick up the new rows
        $provider = new ScheduleServiceProvider($this->app);
        $provider->boot();

        $schedule = $this->app->make(Schedule::class);
        $events = $schedule->events();

        // Should have at least the enabled action
        $this->assertNotEmpty($events);
    }

    public function test_provider_skips_when_table_does_not_exist(): void
    {
        // Drop the table
        Schema::dropIfExists('scheduled_actions');

        // Should not throw
        $provider = new ScheduleServiceProvider($this->app);
        $provider->boot();

        $this->assertTrue(true);
    }
}
