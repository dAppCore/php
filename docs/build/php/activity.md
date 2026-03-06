# Activity Logging

Track user actions, model changes, and system events with GDPR-compliant activity logging.

## Basic Usage

### Enabling Activity Logging

Add the `LogsActivity` trait to your model:

```php
<?php

namespace Mod\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Core\Activity\Concerns\LogsActivity;

class Post extends Model
{
    use LogsActivity;

    protected $fillable = ['title', 'content', 'status'];
}
```

**Automatic Logging:**
- Created events
- Updated events (with changed attributes)
- Deleted events
- Restored events (soft deletes)

### Manual Logging

```php
use Core\Activity\Services\ActivityLogService;

$logger = app(ActivityLogService::class);

// Log custom activity
$logger->log(
    subject: $post,
    event: 'published',
    description: 'Post published to homepage',
    causer: auth()->user()
);

// Log with properties
$logger->log(
    subject: $post,
    event: 'viewed',
    properties: [
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]
);
```

## Activity Model

### Retrieving Activity

```php
use Core\Activity\Models\Activity;

// Get all activity
$activities = Activity::latest()->get();

// Get activity for specific model
$postActivity = Activity::forSubject($post)->get();

// Get activity by user
$userActivity = Activity::causedBy($user)->get();

// Get activity by event
$published = Activity::where('event', 'published')->get();
```

### Activity Attributes

```php
$activity = Activity::latest()->first();

$activity->subject;      // The model that was acted upon
$activity->causer;       // The user who caused the activity
$activity->event;        // Event name (created, updated, deleted, etc.)
$activity->description;  // Human-readable description
$activity->properties;   // Additional data (array)
$activity->created_at;   // When it occurred
```

### Relationships

```php
// Subject (polymorphic)
$post = $activity->subject;

// Causer (polymorphic)
$user = $activity->causer;

// Workspace (if applicable)
$workspace = $activity->workspace;
```

## Activity Scopes

### Filtering Activity

```php
use Core\Activity\Models\Activity;

// By date range
$activities = Activity::query()
    ->whereBetween('created_at', [now()->subDays(7), now()])
    ->get();

// By event type
$activities = Activity::query()
    ->whereIn('event', ['created', 'updated'])
    ->get();

// By workspace
$activities = Activity::query()
    ->where('workspace_id', $workspace->id)
    ->get();

// Complex filters
$activities = Activity::query()
    ->forSubject($post)
    ->causedBy($user)
    ->where('event', 'updated')
    ->latest()
    ->paginate(20);
```

### Custom Scopes

```php
use Core\Activity\Scopes\ActivityScopes;

// Add to Activity model
class Activity extends Model
{
    use ActivityScopes;

    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeWithinDays($query, $days)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

// Usage
$recent = Activity::withinDays(7)
    ->forWorkspace($workspace->id)
    ->get();
```

## Customizing Logged Data

### Controlling What's Logged

```php
class Post extends Model
{
    use LogsActivity;

    // Only log these events
    protected static $recordEvents = ['created', 'published'];

    // Exclude these attributes from change tracking
    protected static $ignoreChangedAttributes = ['views', 'updated_at'];

    // Log only these attributes
    protected static $logAttributes = ['title', 'status'];
}
```

### Custom Descriptions

```php
class Post extends Model
{
    use LogsActivity;

    public function getActivityDescription(string $event): string
    {
        return match($event) {
            'created' => "Created post: {$this->title}",
            'updated' => "Updated post: {$this->title}",
            'published' => "Published post: {$this->title}",
            default => "Post {$event}",
        };
    }
}
```

### Custom Properties

```php
class Post extends Model
{
    use LogsActivity;

    public function getActivityProperties(string $event): array
    {
        return [
            'title' => $this->title,
            'category' => $this->category->name,
            'word_count' => str_word_count($this->content),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
```

## GDPR Compliance

### IP Address Hashing

IP addresses are automatically hashed for privacy:

```php
use Core\Crypt\LthnHash;

// Automatically applied
$activity = Activity::create([
    'properties' => [
        'ip_address' => request()->ip(), // Hashed before storage
    ],
]);

// Verify IP match without storing plaintext
if (LthnHash::check(request()->ip(), $activity->properties['ip_address'])) {
    // IP matches
}
```

### Data Retention

```php
use Core\Activity\Console\ActivityPruneCommand;

// Prune old activity (default: 90 days)
php artisan activity:prune

// Custom retention
php artisan activity:prune --days=30

// Dry run
php artisan activity:prune --dry-run
```

**Scheduled Pruning:**

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('activity:prune')
        ->daily()
        ->at('02:00');
}
```

### Right to Erasure

```php
// Delete all activity for a user
Activity::causedBy($user)->delete();

// Delete activity for specific subject
Activity::forSubject($post)->delete();

// Anonymize instead of delete
Activity::causedBy($user)->update([
    'causer_id' => null,
    'causer_type' => null,
]);
```

## Activity Feed

### Building Activity Feeds

```php
use Core\Activity\Models\Activity;

// User's personal feed
$feed = Activity::causedBy($user)
    ->with(['subject', 'causer'])
    ->latest()
    ->paginate(20);

// Workspace activity feed
$feed = Activity::query()
    ->where('workspace_id', $workspace->id)
    ->whereIn('event', ['created', 'updated', 'published'])
    ->with(['subject', 'causer'])
    ->latest()
    ->paginate(20);
```

### Rendering Activity

```blade
{{-- resources/views/activity/feed.blade.php --}}
@foreach($activities as $activity)
    <div class="activity-item">
        <div class="activity-icon">
            @if($activity->event === 'created')
                <svg>...</svg>
            @elseif($activity->event === 'updated')
                <svg>...</svg>
            @endif
        </div>

        <div class="activity-content">
            <p>
                <strong>{{ $activity->causer?->name ?? 'System' }}</strong>
                {{ $activity->description }}
            </p>
            <time>{{ $activity->created_at->diffForHumans() }}</time>
        </div>
    </div>
@endforeach
```

### Livewire Component

```php
<?php

namespace Core\Activity\View\Modal\Admin;

use Livewire\Component;
use Core\Activity\Models\Activity;

class ActivityFeed extends Component
{
    public $workspaceId;
    public $events = ['created', 'updated', 'deleted'];
    public $days = 7;

    public function render()
    {
        $activities = Activity::query()
            ->when($this->workspaceId, fn($q) => $q->where('workspace_id', $this->workspaceId))
            ->whereIn('event', $this->events)
            ->where('created_at', '>=', now()->subDays($this->days))
            ->with(['subject', 'causer'])
            ->latest()
            ->paginate(20);

        return view('activity::admin.activity-feed', [
            'activities' => $activities,
        ]);
    }
}
```

## Performance Optimization

### Eager Loading

```php
// ✅ Good - eager load relationships
$activities = Activity::query()
    ->with(['subject', 'causer', 'workspace'])
    ->latest()
    ->get();

// ❌ Bad - N+1 queries
$activities = Activity::latest()->get();
foreach ($activities as $activity) {
    echo $activity->causer->name; // Query per iteration
}
```

### Chunking Large Datasets

```php
// Process activity in chunks
Activity::query()
    ->where('created_at', '<', now()->subDays(90))
    ->chunk(1000, function ($activities) {
        foreach ($activities as $activity) {
            $activity->delete();
        }
    });
```

### Queuing Activity Logging

```php
// For high-traffic applications
use Illuminate\Bus\Queueable;

class Post extends Model
{
    use LogsActivity;

    protected static $logActivityQueue = true;

    protected static $logActivityConnection = 'redis';
}
```

## Analytics

### Activity Statistics

```php
use Core\Activity\Services\ActivityLogService;

$analytics = app(ActivityLogService::class);

// Count by event type
$stats = Activity::query()
    ->where('workspace_id', $workspace->id)
    ->whereBetween('created_at', [now()->subDays(30), now()])
    ->groupBy('event')
    ->selectRaw('event, COUNT(*) as count')
    ->get();

// Most active users
$topUsers = Activity::query()
    ->selectRaw('causer_id, causer_type, COUNT(*) as activity_count')
    ->groupBy('causer_id', 'causer_type')
    ->orderByDesc('activity_count')
    ->limit(10)
    ->get();
```

### Audit Reports

```php
// Generate audit trail
$audit = Activity::query()
    ->forSubject($post)
    ->with('causer')
    ->oldest()
    ->get()
    ->map(fn($activity) => [
        'timestamp' => $activity->created_at->toIso8601String(),
        'user' => $activity->causer?->name ?? 'System',
        'event' => $activity->event,
        'changes' => $activity->properties,
    ]);
```

## Best Practices

### 1. Log Meaningful Events

```php
// ✅ Good - business-relevant events
$logger->log($post, 'published', 'Post went live');
$logger->log($order, 'payment_received', 'Customer paid');

// ❌ Bad - too granular
$logger->log($post, 'view_count_incremented', 'Views++');
```

### 2. Include Context

```php
// ✅ Good - rich context
$logger->log($post, 'published', properties: [
    'category' => $post->category->name,
    'scheduled' => $post->published_at->isPast(),
    'author' => $post->author->name,
]);

// ❌ Bad - no context
$logger->log($post, 'published');
```

### 3. Respect Privacy

```php
// ✅ Good - hash sensitive data
$logger->log($user, 'login', properties: [
    'ip_address' => LthnHash::make(request()->ip()),
]);

// ❌ Bad - plaintext IP
$logger->log($user, 'login', properties: [
    'ip_address' => request()->ip(),
]);
```

## Testing

```php
use Tests\TestCase;
use Core\Activity\Models\Activity;

class ActivityTest extends TestCase
{
    public function test_logs_model_creation(): void
    {
        $post = Post::create(['title' => 'Test']);

        $this->assertDatabaseHas('activities', [
            'subject_type' => Post::class,
            'subject_id' => $post->id,
            'event' => 'created',
        ]);
    }

    public function test_logs_changes(): void
    {
        $post = Post::factory()->create(['status' => 'draft']);

        $post->update(['status' => 'published']);

        $activity = Activity::latest()->first();
        $this->assertEquals('published', $activity->properties['status']);
    }
}
```

## Learn More

- [Multi-Tenancy →](/core/tenancy)
- [GDPR Compliance →](/security/overview)
