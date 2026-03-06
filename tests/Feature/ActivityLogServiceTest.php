<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Activity\Services\ActivityLogService;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

class ActivityLogServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ActivityLogService $service;

    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            \Spatie\Activitylog\ActivitylogServiceProvider::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ActivityLogService;
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Set up activity log config
        $app['config']->set('core.activity.enabled', true);
        $app['config']->set('core.activity.log_name', 'test');
        $app['config']->set('core.activity.retention_days', 90);

        // Set up spatie activitylog config
        $app['config']->set('activitylog.default_log_name', 'default');
        $app['config']->set('activitylog.default_auth_driver', null);
        $app['config']->set('activitylog.table_name', 'activities');
        $app['config']->set('activitylog.activity_model', Activity::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Run the activity log migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_fresh_returns_new_instance(): void
    {
        $service = $this->service->fresh();

        $this->assertInstanceOf(ActivityLogService::class, $service);
    }

    public function test_recent_returns_collection(): void
    {
        // Create some test activities
        activity()
            ->withProperties(['test' => true])
            ->log('Test activity 1');

        activity()
            ->withProperties(['test' => true])
            ->log('Test activity 2');

        $activities = $this->service->recent(10);

        $this->assertCount(2, $activities);
    }

    public function test_recent_respects_limit(): void
    {
        // Create 5 activities
        for ($i = 0; $i < 5; $i++) {
            activity()->log("Test activity {$i}");
        }

        $activities = $this->service->recent(3);

        $this->assertCount(3, $activities);
    }

    public function test_search_finds_activities_by_description(): void
    {
        activity()->log('Created a new user account');
        activity()->log('Updated settings');
        activity()->log('Created another user');

        $results = $this->service->search('user')->recent();

        $this->assertCount(2, $results);
    }

    public function test_of_type_filters_by_event(): void
    {
        Activity::create([
            'log_name' => 'test',
            'description' => 'Created item',
            'event' => 'created',
        ]);

        Activity::create([
            'log_name' => 'test',
            'description' => 'Updated item',
            'event' => 'updated',
        ]);

        Activity::create([
            'log_name' => 'test',
            'description' => 'Deleted item',
            'event' => 'deleted',
        ]);

        $created = $this->service->fresh()->ofType('created')->recent();
        $this->assertCount(1, $created);

        $multiple = $this->service->fresh()->ofType(['created', 'updated'])->recent();
        $this->assertCount(2, $multiple);
    }

    public function test_last_days_filters_by_date(): void
    {
        // Create activity from today
        Activity::create([
            'log_name' => 'test',
            'description' => 'Today activity',
            'created_at' => now(),
        ]);

        // Create activity from 10 days ago
        Activity::create([
            'log_name' => 'test',
            'description' => 'Old activity',
            'created_at' => now()->subDays(10),
        ]);

        $recentActivities = $this->service->fresh()->lastDays(7)->recent();

        $this->assertCount(1, $recentActivities);
    }

    public function test_count_returns_activity_count(): void
    {
        activity()->log('Activity 1');
        activity()->log('Activity 2');
        activity()->log('Activity 3');

        $count = $this->service->count();

        $this->assertEquals(3, $count);
    }

    public function test_paginate_returns_paginator(): void
    {
        for ($i = 0; $i < 20; $i++) {
            activity()->log("Activity {$i}");
        }

        $paginated = $this->service->paginate(10);

        $this->assertEquals(20, $paginated->total());
        $this->assertCount(10, $paginated->items());
    }

    public function test_format_returns_correct_structure(): void
    {
        $activity = Activity::create([
            'log_name' => 'test',
            'description' => 'Test description',
            'event' => 'created',
            'properties' => [
                'attributes' => ['name' => 'New Name'],
                'old' => ['name' => 'Old Name'],
                'workspace_id' => 123,
            ],
        ]);

        $formatted = $this->service->format($activity);

        $this->assertArrayHasKey('id', $formatted);
        $this->assertArrayHasKey('event', $formatted);
        $this->assertArrayHasKey('description', $formatted);
        $this->assertArrayHasKey('timestamp', $formatted);
        $this->assertArrayHasKey('relative_time', $formatted);
        $this->assertArrayHasKey('changes', $formatted);
        $this->assertArrayHasKey('workspace_id', $formatted);

        $this->assertEquals('created', $formatted['event']);
        $this->assertEquals('Test description', $formatted['description']);
        $this->assertEquals(123, $formatted['workspace_id']);
        $this->assertEquals(['name' => 'Old Name'], $formatted['changes']['old']);
        $this->assertEquals(['name' => 'New Name'], $formatted['changes']['new']);
    }

    public function test_statistics_returns_aggregated_data(): void
    {
        Activity::create([
            'log_name' => 'test',
            'description' => 'Created',
            'event' => 'created',
            'subject_type' => 'App\Models\User',
        ]);

        Activity::create([
            'log_name' => 'test',
            'description' => 'Updated',
            'event' => 'updated',
            'subject_type' => 'App\Models\Post',
        ]);

        Activity::create([
            'log_name' => 'test',
            'description' => 'Deleted',
            'event' => 'deleted',
            'subject_type' => 'App\Models\User',
        ]);

        $stats = $this->service->statistics();

        $this->assertEquals(3, $stats['total']);
        $this->assertArrayHasKey('by_event', $stats);
        $this->assertArrayHasKey('by_subject', $stats);
        $this->assertEquals(1, $stats['by_event']['created']);
        $this->assertEquals(1, $stats['by_event']['updated']);
        $this->assertEquals(1, $stats['by_event']['deleted']);
    }

    public function test_prune_deletes_old_activities(): void
    {
        // Create old activity
        Activity::create([
            'log_name' => 'test',
            'description' => 'Old activity',
            'created_at' => now()->subDays(100),
        ]);

        // Create recent activity
        Activity::create([
            'log_name' => 'test',
            'description' => 'Recent activity',
            'created_at' => now()->subDays(30),
        ]);

        $deleted = $this->service->prune(90);

        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, Activity::count());
    }

    public function test_prune_with_zero_days_returns_zero(): void
    {
        Activity::create([
            'log_name' => 'test',
            'description' => 'Activity',
            'created_at' => now()->subDays(100),
        ]);

        $deleted = $this->service->prune(0);

        $this->assertEquals(0, $deleted);
        $this->assertEquals(1, Activity::count());
    }

    public function test_in_log_filters_by_log_name(): void
    {
        Activity::create([
            'log_name' => 'system',
            'description' => 'System activity',
        ]);

        Activity::create([
            'log_name' => 'user',
            'description' => 'User activity',
        ]);

        $systemActivities = $this->service->fresh()->inLog('system')->recent();

        $this->assertCount(1, $systemActivities);
        $this->assertEquals('System activity', $systemActivities->first()->description);
    }

    public function test_between_filters_by_date_range(): void
    {
        Activity::create([
            'log_name' => 'test',
            'description' => 'Before range',
            'created_at' => now()->subDays(30),
        ]);

        Activity::create([
            'log_name' => 'test',
            'description' => 'In range',
            'created_at' => now()->subDays(10),
        ]);

        Activity::create([
            'log_name' => 'test',
            'description' => 'After range',
            'created_at' => now()->addDays(1),
        ]);

        $activities = $this->service->fresh()
            ->between(now()->subDays(15), now())
            ->recent();

        $this->assertCount(1, $activities);
        $this->assertEquals('In range', $activities->first()->description);
    }
}
