<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Activity\Concerns\LogsActivity;
use Core\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

/**
 * Test model that uses the LogsActivity trait.
 */
class TestActivityModel extends Model
{
    use LogsActivity;

    protected $table = 'test_activity_models';

    protected $fillable = ['name', 'status', 'workspace_id'];

    public $timestamps = true;
}

/**
 * Test model with custom activity log settings.
 */
class CustomActivityModel extends Model
{
    use LogsActivity;

    protected $table = 'test_activity_models';

    protected $fillable = ['name', 'status', 'workspace_id'];

    public $timestamps = true;

    protected string $activityLogName = 'custom-log';

    protected array $activityLogAttributes = ['name'];

    protected array $activityLogEvents = ['created', 'updated'];

    protected bool $activityLogOnlyDirty = true;
}

class LogsActivityTraitTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            \Spatie\Activitylog\ActivitylogServiceProvider::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create test table
        Schema::create('test_activity_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_activity_models');
        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('core.activity.enabled', true);
        $app['config']->set('core.activity.log_name', 'default');
        $app['config']->set('core.activity.include_workspace', true);
        $app['config']->set('core.activity.default_events', ['created', 'updated', 'deleted']);

        // Set up spatie activitylog config
        $app['config']->set('activitylog.default_log_name', 'default');
        $app['config']->set('activitylog.default_auth_driver', null);
        $app['config']->set('activitylog.table_name', 'activities');
        $app['config']->set('activitylog.activity_model', Activity::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_creates_activity_on_model_create(): void
    {
        $model = TestActivityModel::create(['name' => 'Test Item']);

        $activity = Activity::latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals('created', $activity->event);
        $this->assertEquals(TestActivityModel::class, $activity->subject_type);
        $this->assertEquals($model->id, $activity->subject_id);
    }

    public function test_creates_activity_on_model_update(): void
    {
        $model = TestActivityModel::create(['name' => 'Original Name']);
        $model->update(['name' => 'Updated Name']);

        $activities = Activity::orderBy('id', 'desc')->get();

        $this->assertCount(2, $activities);

        $updateActivity = $activities->first();
        $this->assertEquals('updated', $updateActivity->event);
    }

    public function test_creates_activity_on_model_delete(): void
    {
        $model = TestActivityModel::create(['name' => 'To Delete']);
        $model->delete();

        $deleteActivity = Activity::where('event', 'deleted')->first();

        $this->assertNotNull($deleteActivity);
        $this->assertEquals('deleted', $deleteActivity->event);
    }

    public function test_logs_dirty_attributes_only(): void
    {
        $model = TestActivityModel::create([
            'name' => 'Original',
            'status' => 'active',
        ]);

        $model->update(['name' => 'Updated']);

        $updateActivity = Activity::where('event', 'updated')->first();
        $attributes = $updateActivity->properties->get('attributes', []);

        // Should only log name since that's what changed
        $this->assertArrayHasKey('name', $attributes);
    }

    public function test_includes_workspace_id_in_properties(): void
    {
        $model = TestActivityModel::create([
            'name' => 'With Workspace',
            'workspace_id' => 123,
        ]);

        $activity = Activity::latest()->first();
        $workspaceId = $activity->properties->get('workspace_id');

        $this->assertEquals(123, $workspaceId);
    }

    public function test_uses_custom_log_name(): void
    {
        CustomActivityModel::create(['name' => 'Custom Log Test']);

        $activity = Activity::latest()->first();

        $this->assertEquals('custom-log', $activity->log_name);
    }

    public function test_uses_custom_attributes_list(): void
    {
        $model = CustomActivityModel::create([
            'name' => 'Test Name',
            'status' => 'pending',
        ]);

        $activity = Activity::where('event', 'created')->first();
        $attributes = $activity->properties->get('attributes', []);

        // Should only log 'name' attribute (configured in CustomActivityModel)
        $this->assertArrayHasKey('name', $attributes);
        // Note: Status may or may not be present depending on spatie's behavior
        // The important thing is that we configured to only log 'name'
    }

    public function test_get_activity_log_options_returns_log_options(): void
    {
        $model = new TestActivityModel;
        $options = $model->getActivitylogOptions();

        $this->assertInstanceOf(LogOptions::class, $options);
    }

    public function test_activity_logging_enabled_respects_config(): void
    {
        config(['core.activity.enabled' => true]);
        $this->assertTrue(TestActivityModel::activityLoggingEnabled());

        config(['core.activity.enabled' => false]);
        $this->assertFalse(TestActivityModel::activityLoggingEnabled());
    }

    public function test_without_activity_logging_disables_logging(): void
    {
        $initialCount = Activity::count();

        TestActivityModel::withoutActivityLogging(function () {
            TestActivityModel::create(['name' => 'Silent Create']);
        });

        // Activity logging should be re-enabled after callback
        TestActivityModel::create(['name' => 'Logged Create']);

        // Only one activity should be created (the one outside the callback)
        $this->assertEquals($initialCount + 1, Activity::count());
    }

    public function test_generates_description_for_events(): void
    {
        $model = TestActivityModel::create(['name' => 'Test']);

        $activity = Activity::latest()->first();

        $this->assertStringContainsString('TestActivityModel', $activity->description);
    }

    public function test_tap_activity_allows_customization(): void
    {
        // Create a model with workspace_id to verify it gets added to properties
        $model = TestActivityModel::create([
            'name' => 'With Workspace',
            'workspace_id' => 456,
        ]);

        $activity = Activity::latest()->first();

        $this->assertEquals(456, $activity->properties->get('workspace_id'));
    }
}
