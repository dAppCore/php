# Repository Pattern

Repositories abstract data access logic and provide a consistent interface for querying data.

## When to Use Repositories

Use repositories for:
- Complex query logic
- Multiple data sources
- Abstracting Eloquent/Query Builder
- Testing with fake data

**Don't use repositories for:**
- Simple Eloquent queries (use models directly)
- Single-use queries
- Over-engineering simple applications

## Basic Repository

```php
<?php

namespace Mod\Blog\Repositories;

use Mod\Blog\Models\Post;
use Illuminate\Database\Eloquent\Collection;

class PostRepository
{
    public function findPublished(int $perPage = 20)
    {
        return Post::where('status', 'published')
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    public function findBySlug(string $slug): ?Post
    {
        return Post::where('slug', $slug)
            ->where('status', 'published')
            ->first();
    }

    public function findPopular(int $limit = 10): Collection
    {
        return Post::where('status', 'published')
            ->where('views', '>', 1000)
            ->orderByDesc('views')
            ->limit($limit)
            ->get();
    }

    public function findRecent(int $days = 7, int $limit = 10): Collection
    {
        return Post::where('status', 'published')
            ->where('published_at', '>=', now()->subDays($days))
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }
}
```

**Usage:**

```php
$repository = app(PostRepository::class);
$posts = $repository->findPublished();
$post = $repository->findBySlug('laravel-tutorial');
```

## Repository with Interface

```php
<?php

namespace Mod\Blog\Contracts;

interface PostRepositoryInterface
{
    public function findPublished(int $perPage = 20);
    public function findBySlug(string $slug): ?Post;
    public function findPopular(int $limit = 10): Collection;
}
```

**Implementation:**

```php
<?php

namespace Mod\Blog\Repositories;

use Mod\Blog\Contracts\PostRepositoryInterface;

class EloquentPostRepository implements PostRepositoryInterface
{
    public function findPublished(int $perPage = 20)
    {
        return Post::where('status', 'published')
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    // ... other methods
}
```

**Binding:**

```php
// Service Provider
$this->app->bind(
    PostRepositoryInterface::class,
    EloquentPostRepository::class
);
```

## Repository with Criteria

```php
<?php

namespace Mod\Blog\Repositories;

use Illuminate\Database\Eloquent\Builder;

class PostRepository
{
    protected Builder $query;

    public function __construct()
    {
        $this->query = Post::query();
    }

    public function published(): self
    {
        $this->query->where('status', 'published');
        return $this;
    }

    public function byAuthor(int $authorId): self
    {
        $this->query->where('author_id', $authorId);
        return $this;
    }

    public function inCategory(int $categoryId): self
    {
        $this->query->where('category_id', $categoryId);
        return $this;
    }

    public function recent(int $days = 7): self
    {
        $this->query->where('created_at', '>=', now()->subDays($days));
        return $this;
    }

    public function get(): Collection
    {
        return $this->query->get();
    }

    public function paginate(int $perPage = 20)
    {
        return $this->query->paginate($perPage);
    }
}
```

**Usage:**

```php
$repository = app(PostRepository::class);

// Chain criteria
$posts = $repository
    ->published()
    ->byAuthor($authorId)
    ->recent(30)
    ->paginate();
```

## Repository with Caching

```php
<?php

namespace Mod\Blog\Repositories;

use Illuminate\Support\Facades\Cache;

class CachedPostRepository implements PostRepositoryInterface
{
    public function __construct(
        protected EloquentPostRepository $repository
    ) {}

    public function findPublished(int $perPage = 20)
    {
        $cacheKey = "posts.published.page.{$perPage}";

        return Cache::remember($cacheKey, 3600, function () use ($perPage) {
            return $this->repository->findPublished($perPage);
        });
    }

    public function findBySlug(string $slug): ?Post
    {
        return Cache::remember("posts.slug.{$slug}", 3600, function () use ($slug) {
            return $this->repository->findBySlug($slug);
        });
    }

    public function findPopular(int $limit = 10): Collection
    {
        return Cache::remember("posts.popular.{$limit}", 600, function () use ($limit) {
            return $this->repository->findPopular($limit);
        });
    }
}
```

## Testing with Repositories

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use Mod\Blog\Repositories\PostRepository;

class PostRepositoryTest extends TestCase
{
    public function test_finds_published_posts(): void
    {
        $repository = app(PostRepository::class);

        Post::factory()->create(['status' => 'published']);
        Post::factory()->create(['status' => 'draft']);

        $posts = $repository->findPublished();

        $this->assertCount(1, $posts);
        $this->assertEquals('published', $posts->first()->status);
    }

    public function test_finds_post_by_slug(): void
    {
        $repository = app(PostRepository::class);

        $post = Post::factory()->create([
            'slug' => 'laravel-tutorial',
            'status' => 'published',
        ]);

        $found = $repository->findBySlug('laravel-tutorial');

        $this->assertEquals($post->id, $found->id);
    }
}
```

## Best Practices

### 1. Keep Methods Focused

```php
// ✅ Good - specific method
public function findPublishedInCategory(int $categoryId): Collection
{
    return Post::where('status', 'published')
        ->where('category_id', $categoryId)
        ->get();
}

// ❌ Bad - too generic
public function find(array $criteria): Collection
{
    $query = Post::query();

    foreach ($criteria as $key => $value) {
        $query->where($key, $value);
    }

    return $query->get();
}
```

### 2. Return Collections or Models

```php
// ✅ Good - returns typed result
public function findBySlug(string $slug): ?Post
{
    return Post::where('slug', $slug)->first();
}

// ❌ Bad - returns array
public function findBySlug(string $slug): ?array
{
    return Post::where('slug', $slug)->first()?->toArray();
}
```

### 3. Use Constructor Injection

```php
// ✅ Good - injected
public function __construct(
    protected PostRepositoryInterface $posts
) {}

// ❌ Bad - instantiated
public function __construct()
{
    $this->posts = new PostRepository();
}
```

## Learn More

- [Service Pattern →](/patterns-guide/services)
- [Actions Pattern →](/patterns-guide/actions)
