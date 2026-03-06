# Performance Optimization

Best practices and techniques for optimizing Core PHP Framework applications.

## Database Optimization

### Eager Loading

Prevent N+1 queries with eager loading:

```php
// ❌ Bad - N+1 queries
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->author->name; // Query per post
    echo $post->category->name; // Another query per post
}

// ✅ Good - 3 queries total
$posts = Post::with(['author', 'category'])->get();
foreach ($posts as $post) {
    echo $post->author->name;
    echo $post->category->name;
}
```

### Query Optimization

```php
// ❌ Bad - fetches all columns
$posts = Post::all();

// ✅ Good - only needed columns
$posts = Post::select(['id', 'title', 'created_at'])->get();

// ✅ Good - count instead of loading all
$count = Post::count();

// ❌ Bad
$count = Post::all()->count();

// ✅ Good - exists check
$exists = Post::where('status', 'published')->exists();

// ❌ Bad
$exists = Post::where('status', 'published')->count() > 0;
```

### Chunking Large Datasets

```php
// ❌ Bad - loads everything into memory
$posts = Post::all();
foreach ($posts as $post) {
    $this->process($post);
}

// ✅ Good - process in chunks
Post::chunk(1000, function ($posts) {
    foreach ($posts as $post) {
        $this->process($post);
    }
});

// ✅ Better - lazy collection
Post::lazy()->each(function ($post) {
    $this->process($post);
});
```

### Database Indexes

```php
// Migration
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique(); // Index for lookups
    $table->string('status')->index(); // Index for filtering
    $table->foreignId('workspace_id')->constrained(); // Foreign key index

    // Composite index for common query
    $table->index(['workspace_id', 'status', 'created_at']);
});
```

## Caching Strategies

### Model Caching

```php
use Illuminate\Support\Facades\Cache;

class Post extends Model
{
    public static function findCached(int $id): ?self
    {
        return Cache::remember(
            "posts.{$id}",
            now()->addHour(),
            fn () => self::find($id)
        );
    }

    protected static function booted(): void
    {
        // Invalidate cache on update
        static::updated(fn ($post) => Cache::forget("posts.{$post->id}"));
        static::deleted(fn ($post) => Cache::forget("posts.{$post->id}"));
    }
}
```

### Query Result Caching

```php
// ❌ Bad - no caching
public function getPopularPosts()
{
    return Post::where('views', '>', 1000)
        ->orderByDesc('views')
        ->limit(10)
        ->get();
}

// ✅ Good - cached for 1 hour
public function getPopularPosts()
{
    return Cache::remember('posts.popular', 3600, function () {
        return Post::where('views', '>', 1000)
            ->orderByDesc('views')
            ->limit(10)
            ->get();
    });
}
```

### Cache Tags

```php
// Tag cache for easy invalidation
Cache::tags(['posts', 'popular'])->put('popular-posts', $posts, 3600);

// Clear all posts cache
Cache::tags('posts')->flush();
```

### Redis Caching

```php
// config/cache.php
'default' => env('CACHE_DRIVER', 'redis'),

'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],
```

## Asset Optimization

### CDN Integration

```php
// Use CDN helper
<img src="{{ cdn('images/hero.jpg') }}" alt="Hero">

// With transformations
<img src="{{ cdn('images/hero.jpg', ['width' => 800, 'quality' => 85]) }}">
```

### Image Optimization

```php
use Core\Media\Image\ImageOptimizer;

$optimizer = app(ImageOptimizer::class);

// Automatic optimization
$optimizer->optimize($imagePath, [
    'quality' => 85,
    'max_width' => 1920,
    'strip_exif' => true,
    'convert_to_webp' => true,
]);
```

### Lazy Loading

```blade
{{-- Lazy load images --}}
<img src="{{ cdn($image) }}" loading="lazy" alt="...">

{{-- Lazy load thumbnails --}}
<img src="{{ lazy_thumbnail($image, 'medium') }}" loading="lazy" alt="...">
```

## Code Optimization

### Lazy Loading Modules

Modules only load when their events fire:

```php
// Module Boot.php
public static array $listens = [
    WebRoutesRegistering::class => 'onWebRoutes',
];

// Only loads when WebRoutesRegistering fires
// Saves memory and boot time
```

### Deferred Service Providers

```php
<?php

namespace Mod\Analytics;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;

class AnalyticsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(AnalyticsService::class);
    }

    public function provides(): array
    {
        return [AnalyticsService::class];
    }
}
```

### Configuration Caching

```bash
# Cache configuration
php artisan config:cache

# Clear config cache
php artisan config:clear
```

### Route Caching

```bash
# Cache routes
php artisan route:cache

# Clear route cache
php artisan route:clear
```

## Queue Optimization

### Queue Heavy Operations

```php
// ❌ Bad - slow request
public function store(Request $request)
{
    $post = Post::create($request->validated());

    // Slow operations in request cycle
    $this->generateThumbnails($post);
    $this->generateOgImage($post);
    $this->notifySubscribers($post);

    return redirect()->route('posts.show', $post);
}

// ✅ Good - queued
public function store(Request $request)
{
    $post = Post::create($request->validated());

    // Queue heavy operations
    GenerateThumbnails::dispatch($post);
    GenerateOgImage::dispatch($post);
    NotifySubscribers::dispatch($post);

    return redirect()->route('posts.show', $post);
}
```

### Job Batching

```php
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

Bus::batch([
    new ProcessPost($post1),
    new ProcessPost($post2),
    new ProcessPost($post3),
])->then(function (Batch $batch) {
    // All jobs completed successfully
})->catch(function (Batch $batch, Throwable $e) {
    // First batch job failure
})->finally(function (Batch $batch) {
    // Batch finished
})->dispatch();
```

## Livewire Optimization

### Lazy Loading Components

```blade
{{-- Load component when visible --}}
<livewire:post-list lazy />

{{-- Load on interaction --}}
<livewire:comments lazy on="click" />
```

### Polling Optimization

```php
// ❌ Bad - polls every 1s
<div wire:poll.1s>
    {{ $count }} users online
</div>

// ✅ Good - polls every 30s
<div wire:poll.30s>
    {{ $count }} users online
</div>

// ✅ Better - poll only when visible
<div wire:poll.visible.30s>
    {{ $count }} users online
</div>
```

### Debouncing

```blade
{{-- Debounce search input --}}
<input
    type="search"
    wire:model.live.debounce.500ms="search"
    placeholder="Search..."
>
```

## Response Optimization

### HTTP Caching

```php
// Cache response for 1 hour
return response($content)
    ->header('Cache-Control', 'public, max-age=3600');

// ETag caching
$etag = md5($content);

if ($request->header('If-None-Match') === $etag) {
    return response('', 304);
}

return response($content)
    ->header('ETag', $etag);
```

### Gzip Compression

```php
// config/app.php (handled by middleware)
'middleware' => [
    \Illuminate\Http\Middleware\HandleCors::class,
    \Illuminate\Http\Middleware\ValidatePostSize::class,
    \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
],
```

### Response Streaming

```php
// Stream large files
return response()->streamDownload(function () {
    $handle = fopen('large-file.csv', 'r');
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    fclose($handle);
}, 'download.csv');
```

## Monitoring Performance

### Query Logging

```php
// Enable query log in development
if (app()->isLocal()) {
    DB::enableQueryLog();
}

// View queries
dd(DB::getQueryLog());
```

### Telescope

```bash
# Install Laravel Telescope
composer require laravel/telescope --dev

php artisan telescope:install
php artisan migrate
```

### Clockwork

```bash
# Install Clockwork
composer require itsgoingd/clockwork --dev
```

### Application Performance

```php
// Measure execution time
$start = microtime(true);

// Your code here

$duration = (microtime(true) - $start) * 1000; // milliseconds
Log::info("Operation took {$duration}ms");
```

## Load Testing

### Using Apache Bench

```bash
# 1000 requests, 10 concurrent
ab -n 1000 -c 10 https://example.com/
```

### Using k6

```javascript
// load-test.js
import http from 'k6/http';

export let options = {
  vus: 10, // 10 virtual users
  duration: '30s',
};

export default function () {
  http.get('https://example.com/api/posts');
}
```

```bash
k6 run load-test.js
```

## Best Practices Checklist

### Database
- [ ] Use eager loading to prevent N+1 queries
- [ ] Add indexes to frequently queried columns
- [ ] Use `select()` to limit columns
- [ ] Chunk large datasets
- [ ] Use `exists()` instead of `count() > 0`

### Caching
- [ ] Cache expensive query results
- [ ] Use Redis for session/cache storage
- [ ] Implement cache tags for easy invalidation
- [ ] Set appropriate cache TTLs

### Assets
- [ ] Optimize images before uploading
- [ ] Use CDN for static assets
- [ ] Enable lazy loading for images
- [ ] Generate responsive image sizes

### Code
- [ ] Queue heavy operations
- [ ] Use lazy loading for modules
- [ ] Cache configuration and routes
- [ ] Implement deferred service providers

### Frontend
- [ ] Minimize JavaScript bundle size
- [ ] Debounce user input
- [ ] Use lazy loading for Livewire components
- [ ] Optimize polling intervals

### Monitoring
- [ ] Use Telescope/Clockwork in development
- [ ] Log slow queries
- [ ] Monitor cache hit rates
- [ ] Track job queue performance

## Learn More

- [Configuration →](/packages/core/configuration)
- [CDN Integration →](/packages/core/cdn)
- [Media Processing →](/packages/core/media)
