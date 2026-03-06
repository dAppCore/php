# Actions Pattern

Actions are single-purpose classes that encapsulate business logic. They provide a clean, testable, and reusable way to handle complex operations.

## Why Actions?

### Traditional Controller (Fat Controllers)

```php
class PostController extends Controller
{
    public function store(Request $request)
    {
        // Validation
        $validated = $request->validate([/*...*/]);

        // Business logic mixed with controller concerns
        $slug = Str::slug($validated['title']);

        if (Post::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(5);
        }

        $post = Post::create([
            'title' => $validated['title'],
            'slug' => $slug,
            'content' => $validated['content'],
            'workspace_id' => auth()->user()->workspace_id,
        ]);

        if ($request->has('tags')) {
            $post->tags()->sync($validated['tags']);
        }

        event(new PostCreated($post));

        Cache::tags(['posts'])->flush();

        return redirect()->route('posts.show', $post);
    }
}
```

**Problems:**
- Business logic tied to HTTP layer
- Hard to reuse from console, jobs, or tests
- Difficult to test in isolation
- Controller responsibilities bloat

### Actions Pattern (Clean Separation)

```php
class PostController extends Controller
{
    public function store(StorePostRequest $request)
    {
        $post = CreatePost::run($request->validated());

        return redirect()->route('posts.show', $post);
    }
}

class CreatePost
{
    use Action;

    public function handle(array $data): Post
    {
        $slug = $this->generateUniqueSlug($data['title']);

        $post = Post::create([
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $data['content'],
        ]);

        if (isset($data['tags'])) {
            $post->tags()->sync($data['tags']);
        }

        event(new PostCreated($post));
        Cache::tags(['posts'])->flush();

        return $post;
    }

    private function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);

        if (Post::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(5);
        }

        return $slug;
    }
}
```

**Benefits:**
- Business logic isolated from HTTP concerns
- Reusable from anywhere (controllers, jobs, commands, tests)
- Easy to test
- Single responsibility
- Dependency injection support

## Creating Actions

### Basic Action

```php
<?php

namespace Mod\Blog\Actions;

use Core\Actions\Action;
use Mod\Blog\Models\Post;

class PublishPost
{
    use Action;

    public function handle(Post $post): Post
    {
        $post->update([
            'published_at' => now(),
            'status' => 'published',
        ]);

        return $post;
    }
}
```

### Using Actions

```php
// Static call (recommended)
$post = PublishPost::run($post);

// Instance call
$action = new PublishPost();
$post = $action->handle($post);

// Via container (with DI)
$post = app(PublishPost::class)->handle($post);
```

## Dependency Injection

Actions support constructor dependency injection:

```php
<?php

namespace Mod\Blog\Actions;

use Core\Actions\Action;
use Mod\Blog\Models\Post;
use Mod\Blog\Repositories\PostRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Cache\Repository as Cache;

class CreatePost
{
    use Action;

    public function __construct(
        private PostRepository $posts,
        private Dispatcher $events,
        private Cache $cache,
    ) {}

    public function handle(array $data): Post
    {
        $post = $this->posts->create($data);

        $this->events->dispatch(new PostCreated($post));
        $this->cache->tags(['posts'])->flush();

        return $post;
    }
}
```

## Action Return Types

### Returning Models

```php
class CreatePost
{
    use Action;

    public function handle(array $data): Post
    {
        return Post::create($data);
    }
}

$post = CreatePost::run($data);
```

### Returning Collections

```php
class GetRecentPosts
{
    use Action;

    public function handle(int $limit = 10): Collection
    {
        return Post::published()
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }
}

$posts = GetRecentPosts::run(5);
```

### Returning Boolean

```php
class DeletePost
{
    use Action;

    public function handle(Post $post): bool
    {
        return $post->delete();
    }
}

$deleted = DeletePost::run($post);
```

### Returning DTOs

```php
class AnalyzePost
{
    use Action;

    public function handle(Post $post): PostAnalytics
    {
        return new PostAnalytics(
            views: $post->views()->count(),
            averageReadTime: $this->calculateReadTime($post),
            engagement: $this->calculateEngagement($post),
        );
    }
}

$analytics = AnalyzePost::run($post);
echo $analytics->views;
```

## Complex Actions

### Multi-Step Actions

```php
class ImportPostsFromWordPress
{
    use Action;

    public function __construct(
        private WordPressClient $client,
        private CreatePost $createPost,
        private AttachCategories $attachCategories,
        private ImportMedia $importMedia,
    ) {}

    public function handle(string $siteUrl, array $options = []): ImportResult
    {
        $posts = $this->client->fetchPosts($siteUrl);
        $imported = [];
        $errors = [];

        foreach ($posts as $wpPost) {
            try {
                DB::transaction(function () use ($wpPost, &$imported) {
                    // Create post
                    $post = $this->createPost->handle([
                        'title' => $wpPost['title'],
                        'content' => $wpPost['content'],
                        'published_at' => $wpPost['date'],
                    ]);

                    // Import media
                    if ($wpPost['featured_image']) {
                        $this->importMedia->handle($post, $wpPost['featured_image']);
                    }

                    // Attach categories
                    $this->attachCategories->handle($post, $wpPost['categories']);

                    $imported[] = $post;
                });
            } catch (\Exception $e) {
                $errors[] = [
                    'post' => $wpPost['title'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return new ImportResult(
            imported: collect($imported),
            errors: collect($errors),
        );
    }
}
```

### Actions with Validation

```php
class UpdatePost
{
    use Action;

    public function __construct(
        private ValidatePostData $validator,
    ) {}

    public function handle(Post $post, array $data): Post
    {
        // Validate before processing
        $validated = $this->validator->handle($data);

        $post->update($validated);

        return $post->fresh();
    }
}

class ValidatePostData
{
    use Action;

    public function handle(array $data): array
    {
        return validator($data, [
            'title' => 'required|max:255',
            'content' => 'required',
            'published_at' => 'nullable|date',
        ])->validate();
    }
}
```

## Action Patterns

### Command Pattern

Actions are essentially the Command pattern:

```php
interface ActionInterface
{
    public function handle(...$params);
}

// Each action is a command
class PublishPost implements ActionInterface { }
class UnpublishPost implements ActionInterface { }
class SchedulePost implements ActionInterface { }
```

### Pipeline Pattern

Chain multiple actions:

```php
class ProcessNewPost
{
    use Action;

    public function handle(array $data): Post
    {
        return Pipeline::send($data)
            ->through([
                ValidatePostData::class,
                SanitizeContent::class,
                CreatePost::class,
                GenerateExcerpt::class,
                GenerateSocialImages::class,
                NotifySubscribers::class,
            ])
            ->thenReturn();
    }
}
```

### Strategy Pattern

Different strategies as actions:

```php
interface PublishStrategy
{
    public function publish(Post $post): void;
}

class PublishImmediately implements PublishStrategy
{
    public function publish(Post $post): void
    {
        $post->update(['published_at' => now()]);
    }
}

class ScheduleForLater implements PublishStrategy
{
    public function publish(Post $post): void
    {
        PublishPostJob::dispatch($post)
            ->delay($post->scheduled_at);
    }
}

class PublishPost
{
    use Action;

    public function handle(Post $post, PublishStrategy $strategy): void
    {
        $strategy->publish($post);
    }
}
```

## Testing Actions

### Unit Testing

Test actions in isolation:

```php
<?php

namespace Tests\Unit\Mod\Blog\Actions;

use Tests\TestCase;
use Mod\Blog\Actions\CreatePost;
use Mod\Blog\Models\Post;

class CreatePostTest extends TestCase
{
    public function test_creates_post_with_valid_data(): void
    {
        $data = [
            'title' => 'Test Post',
            'content' => 'Test content',
        ];

        $post = CreatePost::run($data);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertEquals('Test Post', $post->title);
        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
        ]);
    }

    public function test_generates_unique_slug(): void
    {
        Post::factory()->create(['slug' => 'test-post']);

        $post = CreatePost::run([
            'title' => 'Test Post',
            'content' => 'Content',
        ]);

        $this->assertNotEquals('test-post', $post->slug);
        $this->assertStringStartsWith('test-post-', $post->slug);
    }
}
```

### Mocking Dependencies

```php
public function test_dispatches_event_after_creation(): void
{
    Event::fake();

    $post = CreatePost::run([
        'title' => 'Test Post',
        'content' => 'Content',
    ]);

    Event::assertDispatched(PostCreated::class, function ($event) use ($post) {
        return $event->post->id === $post->id;
    });
}
```

### Integration Testing

```php
public function test_import_creates_posts_from_wordpress(): void
{
    Http::fake([
        'wordpress.example.com/*' => Http::response([
            [
                'title' => 'WP Post 1',
                'content' => 'Content 1',
                'date' => '2026-01-01',
            ],
            [
                'title' => 'WP Post 2',
                'content' => 'Content 2',
                'date' => '2026-01-02',
            ],
        ]),
    ]);

    $result = ImportPostsFromWordPress::run('wordpress.example.com');

    $this->assertCount(2, $result->imported);
    $this->assertCount(0, $result->errors);
    $this->assertEquals(2, Post::count());
}
```

## Action Composition

### Composing Actions

Build complex operations from simple actions:

```php
class PublishBlogPost
{
    use Action;

    public function __construct(
        private UpdatePost $updatePost,
        private GenerateOgImage $generateImage,
        private NotifySubscribers $notifySubscribers,
        private PingSearchEngines $pingSearchEngines,
    ) {}

    public function handle(Post $post): Post
    {
        // Update post status
        $post = $this->updatePost->handle($post, [
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Generate social images
        $this->generateImage->handle($post);

        // Notify subscribers
        dispatch(fn () => $this->notifySubscribers->handle($post))
            ->afterResponse();

        // Ping search engines
        dispatch(fn () => $this->pingSearchEngines->handle($post))
            ->afterResponse();

        return $post;
    }
}
```

### Conditional Execution

```php
class ProcessPost
{
    use Action;

    public function handle(Post $post, array $options = []): Post
    {
        if ($options['publish'] ?? false) {
            PublishPost::run($post);
        }

        if ($options['notify'] ?? false) {
            NotifySubscribers::run($post);
        }

        if ($options['generate_images'] ?? true) {
            GenerateSocialImages::run($post);
        }

        return $post;
    }
}
```

## Best Practices

### 1. Single Responsibility

Each action should do one thing:

```php
// ✅ Good - focused actions
class CreatePost { }
class PublishPost { }
class NotifySubscribers { }

// ❌ Bad - does too much
class CreateAndPublishPostAndNotifySubscribers { }
```

### 2. Meaningful Names

Use descriptive verb-noun names:

```php
// ✅ Good names
class CreatePost { }
class UpdatePost { }
class DeletePost { }
class PublishPost { }
class UnpublishPost { }

// ❌ Bad names
class PostAction { }
class HandlePost { }
class DoStuff { }
```

### 3. Return Values

Always return something useful:

```php
// ✅ Good - returns created model
public function handle(array $data): Post
{
    return Post::create($data);
}

// ❌ Bad - returns nothing
public function handle(array $data): void
{
    Post::create($data);
}
```

### 4. Idempotency

Make actions idempotent when possible:

```php
class PublishPost
{
    use Action;

    public function handle(Post $post): Post
    {
        // Idempotent - safe to call multiple times
        if ($post->isPublished()) {
            return $post;
        }

        $post->update(['published_at' => now()]);

        return $post;
    }
}
```

### 5. Type Hints

Always use type hints:

```php
// ✅ Good - clear types
public function handle(Post $post, array $data): Post

// ❌ Bad - no types
public function handle($post, $data)
```

## Common Use Cases

### CRUD Operations

```php
class CreatePost { }
class UpdatePost { }
class DeletePost { }
class RestorePost { }
```

### State Transitions

```php
class PublishPost { }
class UnpublishPost { }
class ArchivePost { }
class SchedulePost { }
```

### Data Processing

```php
class ImportPosts { }
class ExportPosts { }
class SyncPosts { }
class MigratePosts { }
```

### Calculations

```php
class CalculatePostStatistics { }
class GeneratePostSummary { }
class AnalyzePostPerformance { }
```

### External Integrations

```php
class SyncToWordPress { }
class PublishToMedium { }
class ShareOnSocial { }
```

## Action vs Service

### When to Use Actions

- Single, focused operations
- No state management needed
- Reusable across contexts

### When to Use Services

- Multiple related operations
- Stateful operations
- Facade for complex subsystem

```php
// Action - single operation
class CreatePost
{
    use Action;

    public function handle(array $data): Post
    {
        return Post::create($data);
    }
}

// Service - multiple operations, state
class BlogService
{
    private Collection $posts;

    public function getRecentPosts(int $limit): Collection
    {
        return $this->posts ??= Post::latest()->limit($limit)->get();
    }

    public function getPopularPosts(int $limit): Collection { }
    public function searchPosts(string $query): Collection { }
    public function getPostsByCategory(Category $category): Collection { }
}
```

## Learn More

- [Service Layer](/patterns-guide/services)
- [Repository Pattern](/patterns-guide/repositories)
- [Testing Actions](/testing/actions)
