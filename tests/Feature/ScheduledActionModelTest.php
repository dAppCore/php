<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Actions\ScheduledAction;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ScheduledActionModelTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_model_can_be_created(): void
    {
        $action = ScheduledAction::create([
            'action_class' => 'App\\Actions\\TestAction',
            'frequency' => 'dailyAt:09:00',
            'timezone' => 'Europe/London',
            'without_overlapping' => true,
            'run_in_background' => true,
            'is_enabled' => true,
        ]);

        $this->assertDatabaseHas('scheduled_actions', [
            'action_class' => 'App\\Actions\\TestAction',
            'frequency' => 'dailyAt:09:00',
        ]);
    }

    public function test_enabled_scope(): void
    {
        ScheduledAction::create([
            'action_class' => 'App\\Actions\\Enabled',
            'frequency' => 'hourly',
            'is_enabled' => true,
        ]);
        ScheduledAction::create([
            'action_class' => 'App\\Actions\\Disabled',
            'frequency' => 'hourly',
            'is_enabled' => false,
        ]);

        $enabled = ScheduledAction::enabled()->get();
        $this->assertCount(1, $enabled);
        $this->assertSame('App\\Actions\\Enabled', $enabled->first()->action_class);
    }

    public function test_frequency_method_parses_simple_frequency(): void
    {
        $action = new ScheduledAction(['frequency' => 'everyMinute']);
        $this->assertSame('everyMinute', $action->frequencyMethod());
        $this->assertSame([], $action->frequencyArgs());
    }

    public function test_frequency_method_parses_frequency_with_args(): void
    {
        $action = new ScheduledAction(['frequency' => 'dailyAt:09:00']);
        $this->assertSame('dailyAt', $action->frequencyMethod());
        $this->assertSame(['09:00'], $action->frequencyArgs());
    }

    public function test_frequency_method_parses_multiple_args(): void
    {
        $action = new ScheduledAction(['frequency' => 'weeklyOn:1,09:00']);
        $this->assertSame('weeklyOn', $action->frequencyMethod());
        $this->assertSame([1, '09:00'], $action->frequencyArgs());
    }

    public function test_mark_run_updates_last_run_at(): void
    {
        $action = ScheduledAction::create([
            'action_class' => 'App\\Actions\\Runnable',
            'frequency' => 'hourly',
            'is_enabled' => true,
        ]);

        $this->assertNull($action->last_run_at);

        $action->markRun();
        $action->refresh();

        $this->assertNotNull($action->last_run_at);
    }
}
