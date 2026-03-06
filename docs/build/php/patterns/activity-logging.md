# Activity Logging

Core PHP Framework provides comprehensive activity logging to track changes to your models and user actions. Built on Spatie's `laravel-activitylog`, it adds workspace-scoped logging and automatic cleanup.

## Overview

Activity logging helps you:

- Track who changed what and when
- Maintain audit trails for compliance
- Debug issues by reviewing historical changes
- Display activity feeds to users
- Revert changes when needed

## Setup

### Installation

The activity log package is included in Core PHP:

```bash
composer require spatie/laravel-activitylog
```

### Migration

Run migrations to create the `activity_log` table:

```bash
php artisan migrate
```

### Configuration

Publish and customize the configuration:

```bash
php artisan vendor:publish --tag=activitylog
```

Core PHP extends the default configuration:

```php
// config/core.php
'activity' => [
    'enabled' => env('ACTIVITY_LOG_ENABLED', true),
    'retention_days' => env('ACTIVITY_RETENTION_DAYS', 90),
    'cleanup_enabled' => true,
    'log_ip_address' => false, // GDPR compliance
],
```

## Basic Usage

### Adding Logging to Models

Use the `LogsActivity` trait:

```php
<?php

namespace Mod\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Core\Activity\Concerns\LogsActivity;

class Post extends Model
{
    use LogsActivity;

    protected $fillable = ['title', 'content', 'published_at'];

    // Specify which attributes to log
    protected array $activityLogAttributes = ['title', 'content', 'published_at'];

    // Optionally, log all fillable attributes
    // protected static $logFillable = true;
}
```

### Automatic Logging

Changes are logged automatically:

```php
$post = Post::create([
    'title' => 'My First Post',
    'content' => 'Hello world!',
]);
// Activity logged: "created" event

$post->update(['title' => 'Updated Title']);
// Activity logged: "updated" event with changes

$post->delete();
// Activity logged: "deleted" event
```

### Manual Logging

Log custom activities:

```php
activity()
    ->performedOn($post)
    ->causedBy(auth()->user())
    ->withProperties(['custom' => 'data'])
    ->log('published');

// Or use the helper on the model
$post->logActivity('published', ['published_at' => now()]);
```

## Configuration Options

### Log Attributes

Specify which attributes to track:

```php
class Post extends Model
{
    use LogsActivity;

    // Log specific attributes
    protected array $activityLogAttributes = ['title', 'content', 'status'];

    // Log all fillable attributes
    protected static $logFillable = true;

    // Log all attributes
    protected static $logAttributes = ['*'];

    // Log only dirty (changed) attributes
    protected static $logOnlyDirty = true;

    // Don't log these attributes
    protected static $logAttributesToIgnore = ['updated_at', 'view_count'];
}
```

### Log Events

Control which events trigger logging:

```php
class Post extends Model
{
    use LogsActivity;

    // Log only these events (default: all)
    protected static $recordEvents = ['created', 'updated', 'deleted'];

    // Don't log these events
    protected static $ignoreEvents = ['retrieved'];
}
```

### Custom Log Names

Organize activities by type:

```php
class Post extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'content'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Post {$eventName}")
            ->useLogName('blog');
    }
}
```

## Retrieving Activity

### Get All Activity

```php
// All activity in the system
$activities = Activity::all();

// Recent activity
$recent = Activity::latest()->limit(10)->get();

// Activity for specific model
$postActivity = Activity::forSubject($post)->get();

// Activity by specific user
$userActivity = Activity::causedBy($user)->get();
```

### Filtering Activity

```php
// By log name
$blogActivity = Activity::inLog('blog')->get();

// By description
$publishedPosts = Activity::where('description', 'published')->get();

// By date range
$recentActivity = Activity::whereBetween('created_at', [
    now()->subDays(7),
    now(),
])->get();

// By properties
$activity = Activity::whereJsonContains('properties->status', 'published')->get();
```

### Activity Scopes

Core PHP adds workspace scoping:

```php
use Core\Activity\Scopes\ActivityScopes;

// Activity for current workspace
$workspaceActivity = Activity::forCurrentWorkspace()->get();

// Activity for specific workspace
$activity = Activity::forWorkspace($workspace)->get();

// Activity for specific subject type
$postActivity = Activity::forSubjectType(Post::class)->get();
```

## Activity Properties

### Storing Extra Data

```php
activity()
    ->performedOn($post)
    ->withProperties([
        'old_status' => 'draft',
        'new_status' => 'published',
        'scheduled_at' => $post->published_at,
        'notified_subscribers' => true,
    ])
    ->log('published');
```

### Retrieving Properties

```php
$activity = Activity::latest()->first();

$properties = $activity->properties;
$oldStatus = $activity->properties['old_status'] ?? null;

// Access as object
$newStatus = $activity->properties->new_status;
```

### Changes Tracking

View before/after values:

```php
$post->update(['title' => 'New Title']);

$activity = Activity::forSubject($post)->latest()->first();

$changes = $activity->changes();
// [
//     'attributes' => ['title' => 'New Title'],
//     'old' => ['title' => 'Old Title']
// ]
```

## Activity Presentation

### Display Activity Feed

```php
// Controller
public function activityFeed()
{
    $activities = Activity::with(['causer', 'subject'])
        ->forCurrentWorkspace()
        ->latest()
        ->paginate(20);

    return view('activity-feed', compact('activities'));
}
```

```blade
<!-- View -->
@foreach($activities as $activity)
    <div class="activity-item">
        <div class="activity-icon">
            @if($activity->description === 'created')
                <span class="text-green-500">+</span>
            @elseif($activity->description === 'deleted')
                <span class="text-red-500">×</span>
            @else
                <span class="text-blue-500">•</span>
            @endif
        </div>

        <div class="activity-content">
            <p>
                <strong>{{ $activity->causer->name ?? 'System' }}</strong>
                {{ $activity->description }}
                <em>{{ class_basename($activity->subject_type) }}</em>
                @if($activity->subject)
                    <a href="{{ route('posts.show', $activity->subject) }}">
                        {{ $activity->subject->title }}
                    </a>
                @endif
            </p>
            <time>{{ $activity->created_at->diffForHumans() }}</time>
        </div>
    </div>
@endforeach
```

### Custom Descriptions

Make descriptions more readable:

```php
class Post extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->setDescriptionForEvent(function(string $eventName) {
                return match($eventName) {
                    'created' => 'created post "' . $this->title . '"',
                    'updated' => 'updated post "' . $this->title . '"',
                    'deleted' => 'deleted post "' . $this->title . '"',
                    'published' => 'published post "' . $this->title . '"',
                    default => $eventName . ' post',
                };
            });
    }
}
```

## Workspace Isolation

### Automatic Scoping

Activity is automatically scoped to workspaces:

```php
// Only returns activity for current workspace
$activity = Activity::forCurrentWorkspace()->get();

// Explicitly query another workspace (admin only)
if (auth()->user()->isSuperAdmin()) {
    $activity = Activity::forWorkspace($otherWorkspace)->get();
}
```

### Cross-Workspace Activity

```php
// Admin reports across all workspaces
$systemActivity = Activity::withoutGlobalScopes()->get();

// Activity counts by workspace
$stats = Activity::withoutGlobalScopes()
    ->select('workspace_id', DB::raw('count(*) as count'))
    ->groupBy('workspace_id')
    ->get();
```

## Activity Cleanup

### Automatic Pruning

Configure automatic cleanup of old activity:

```php
// config/core.php
'activity' => [
    'retention_days' => 90,
    'cleanup_enabled' => true,
],
```

Schedule the cleanup command:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('activity:prune')
        ->daily()
        ->at('02:00');
}
```

### Manual Pruning

```bash
# Delete activity older than configured retention period
php artisan activity:prune

# Delete activity older than specific number of days
php artisan activity:prune --days=30

# Dry run (see what would be deleted)
php artisan activity:prune --dry-run
```

### Selective Deletion

```php
// Delete activity for specific model
Activity::forSubject($post)->delete();

// Delete activity by log name
Activity::inLog('temporary')->delete();

// Delete activity older than date
Activity::where('created_at', '<', now()->subMonths(6))->delete();
```

## Advanced Usage

### Batch Logging

Log multiple changes as a single activity:

```php
activity()->enableLogging();

// Disable automatic logging temporarily
activity()->disableLogging();

Post::create([/*...*/]); // Not logged
Post::create([/*...*/]); // Not logged
Post::create([/*...*/]); // Not logged

// Re-enable and log batch operation
activity()->enableLogging();

activity()
    ->performedOn($workspace)
    ->log('imported 100 posts');
```

### Custom Activity Models

Extend the activity model:

```php
<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity as BaseActivity;

class Activity extends BaseActivity
{
    public function scopePublic($query)
    {
        return $query->where('properties->public', true);
    }

    public function wasSuccessful(): bool
    {
        return $this->properties['success'] ?? true;
    }
}
```

Update config:

```php
// config/activitylog.php
'activity_model' => App\Models\Activity::class,
```

### Queued Logging

Log activity in the background for performance:

```php
// In a job or listener
dispatch(function () use ($post, $user) {
    activity()
        ->performedOn($post)
        ->causedBy($user)
        ->log('processed');
})->afterResponse();
```

## GDPR Compliance

### Anonymize User Data

Don't log personally identifiable information:

```php
// config/core.php
'activity' => [
    'log_ip_address' => false,
    'anonymize_after_days' => 30,
],
```

### Anonymization

```php
class AnonymizeOldActivity
{
    public function handle(): void
    {
        Activity::where('created_at', '<', now()->subDays(30))
            ->whereNotNull('causer_id')
            ->update([
                'causer_id' => null,
                'causer_type' => null,
                'properties->ip_address' => null,
            ]);
    }
}
```

### User Data Deletion

Delete user's activity when account is deleted:

```php
class User extends Model
{
    protected static function booted()
    {
        static::deleting(function ($user) {
            // Delete or anonymize activity
            Activity::causedBy($user)->delete();
        });
    }
}
```

## Performance Optimization

### Eager Loading

Prevent N+1 queries:

```php
$activities = Activity::with(['causer', 'subject'])
    ->latest()
    ->paginate(20);
```

### Selective Logging

Only log important changes:

```php
class Post extends Model
{
    use LogsActivity;

    // Only log changes to these critical fields
    protected array $activityLogAttributes = ['title', 'published_at', 'status'];

    // Only log when attributes actually change
    protected static $logOnlyDirty = true;
}
```

### Disable Logging Temporarily

```php
// Disable for bulk operations
activity()->disableLogging();

Post::query()->update(['migrated' => true]);

activity()->enableLogging();
```

## Testing

### Testing Activity Logging

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Mod\Blog\Models\Post;
use Spatie\Activitylog\Models\Activity;

class PostActivityTest extends TestCase
{
    public function test_logs_post_creation(): void
    {
        $post = Post::create([
            'title' => 'Test Post',
            'content' => 'Test content',
        ]);

        $activity = Activity::forSubject($post)->first();

        $this->assertEquals('created', $activity->description);
        $this->assertEquals(auth()->id(), $activity->causer_id);
    }

    public function test_logs_attribute_changes(): void
    {
        $post = Post::factory()->create(['title' => 'Original']);

        $post->update(['title' => 'Updated']);

        $activity = Activity::forSubject($post)->latest()->first();

        $this->assertEquals('updated', $activity->description);
        $this->assertEquals('Original', $activity->changes()['old']['title']);
        $this->assertEquals('Updated', $activity->changes()['attributes']['title']);
    }
}
```

## Best Practices

### 1. Log Business Events

```php
// ✅ Good - meaningful business events
$post->logActivity('published', ['published_at' => now()]);
$post->logActivity('featured', ['featured_until' => $date]);

// ❌ Bad - technical implementation details
$post->logActivity('database_updated');
```

### 2. Include Context

```php
// ✅ Good - rich context
activity()
    ->performedOn($post)
    ->withProperties([
        'published_at' => $post->published_at,
        'notification_sent' => true,
        'subscribers_count' => $subscribersCount,
    ])
    ->log('published');

// ❌ Bad - minimal context
activity()->performedOn($post)->log('published');
```

### 3. Use Descriptive Log Names

```php
// ✅ Good - organized by domain
activity()->useLog('blog')->log('post published');
activity()->useLog('commerce')->log('order placed');

// ❌ Bad - generic log name
activity()->useLog('default')->log('thing happened');
```

## Learn More

- [Activity Feed UI](/packages/admin#activity-feed)
- [GDPR Compliance](/security/gdpr)
- [Testing Activity](/testing/activity-logging)
