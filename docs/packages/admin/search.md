# Global Search

The Admin package provides a unified global search system that searches across all registered modules and resources.

## Overview

Global search features:
- Search across multiple modules
- Keyboard shortcut (Cmd/Ctrl + K)
- Real-time results
- Category grouping
- Icon support
- Direct navigation

## Registering Search Providers

### Basic Search Provider

```php
<?php

namespace Mod\Blog\Search;

use Core\Admin\Search\Contracts\SearchProvider;
use Core\Admin\Search\SearchResult;
use Mod\Blog\Models\Post;

class PostSearchProvider implements SearchProvider
{
    public function search(string $query): array
    {
        return Post::where('title', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(fn (Post $post) => new SearchResult(
                title: $post->title,
                description: $post->excerpt,
                url: route('admin.blog.posts.edit', $post),
                icon: 'document-text',
                category: 'Blog Posts'
            ))
            ->toArray();
    }

    public function getCategory(): string
    {
        return 'Blog';
    }

    public function getPriority(): int
    {
        return 50; // Higher = appears first
    }
}
```

### Register in Boot.php

```php
<?php

namespace Mod\Blog;

use Core\Events\AdminPanelBooting;
use Mod\Blog\Search\PostSearchProvider;

class Boot
{
    public static array $listens = [
        AdminPanelBooting::class => 'onAdminPanel',
    ];

    public function onAdminPanel(AdminPanelBooting $event): void
    {
        $event->search(new PostSearchProvider());
    }
}
```

## Search Result

The `SearchResult` class defines how results appear:

```php
use Core\Admin\Search\SearchResult;

new SearchResult(
    title: 'My Blog Post',                    // Required
    description: 'This is a blog post about...',  // Optional
    url: route('admin.blog.posts.edit', $post),   // Required
    icon: 'document-text',                         // Optional
    category: 'Blog Posts',                        // Optional
    metadata: [                                     // Optional
        'Status' => 'Published',
        'Author' => $post->author->name,
    ]
);
```

**Properties:**
- `title` (string, required) - Primary title
- `description` (string, optional) - Subtitle/excerpt
- `url` (string, required) - Link URL
- `icon` (string, optional) - Heroicon name
- `category` (string, optional) - Result category
- `metadata` (array, optional) - Additional key-value pairs

## Advanced Search Providers

### With Highlighting

```php
public function search(string $query): array
{
    return Post::where('title', 'like', "%{$query}%")
        ->get()
        ->map(function (Post $post) use ($query) {
            // Highlight matching text
            $title = str_ireplace(
                $query,
                "<mark>{$query}</mark>",
                $post->title
            );

            return new SearchResult(
                title: $title,
                description: $post->excerpt,
                url: route('admin.blog.posts.edit', $post),
                icon: 'document-text'
            );
        })
        ->toArray();
}
```

### Multi-Field Search

```php
public function search(string $query): array
{
    return Post::where(function ($q) use ($query) {
        $q->where('title', 'like', "%{$query}%")
          ->orWhere('content', 'like', "%{$query}%")
          ->orWhere('slug', 'like', "%{$query}%");
    })
    ->limit(5)
    ->get()
    ->map(fn ($post) => new SearchResult(
        title: $post->title,
        description: "Slug: {$post->slug}",
        url: route('admin.blog.posts.edit', $post),
        icon: 'document-text',
        category: 'Posts'
    ))
    ->toArray();
}
```

### With Relevance Scoring

```php
public function search(string $query): array
{
    $posts = Post::selectRaw("
            *,
            CASE
                WHEN title LIKE ? THEN 3
                WHEN excerpt LIKE ? THEN 2
                WHEN content LIKE ? THEN 1
                ELSE 0
            END as relevance
        ", ["%{$query}%", "%{$query}%", "%{$query}%"])
        ->having('relevance', '>', 0)
        ->orderBy('relevance', 'desc')
        ->limit(5)
        ->get();

    return $posts->map(fn ($post) => new SearchResult(
        title: $post->title,
        description: $post->excerpt,
        url: route('admin.blog.posts.edit', $post),
        icon: 'document-text'
    ))->toArray();
}
```

### Search with Relationships

```php
public function search(string $query): array
{
    return Post::with('author', 'category')
        ->where('title', 'like', "%{$query}%")
        ->limit(5)
        ->get()
        ->map(fn ($post) => new SearchResult(
            title: $post->title,
            description: $post->excerpt,
            url: route('admin.blog.posts.edit', $post),
            icon: 'document-text',
            category: 'Posts',
            metadata: [
                'Author' => $post->author->name,
                'Category' => $post->category->name,
                'Status' => ucfirst($post->status),
            ]
        ))
        ->toArray();
}
```

## Search Analytics

Track search queries:

```php
<?php

namespace Mod\Blog\Search;

use Core\Admin\Search\Contracts\SearchProvider;
use Core\Admin\Search\SearchResult;
use Core\Search\Analytics\SearchAnalytics;

class PostSearchProvider implements SearchProvider
{
    public function __construct(
        protected SearchAnalytics $analytics
    ) {}

    public function search(string $query): array
    {
        // Record search
        $this->analytics->recordSearch($query, 'admin', 'posts');

        $results = Post::where('title', 'like', "%{$query}%")
            ->limit(5)
            ->get();

        // Record result count
        $this->analytics->recordResults($query, $results->count());

        return $results->map(fn ($post) => new SearchResult(
            title: $post->title,
            url: route('admin.blog.posts.edit', $post)
        ))->toArray();
    }
}
```

## Multiple Providers

Register multiple providers for different resources:

```php
public function onAdminPanel(AdminPanelBooting $event): void
{
    $event->search(new PostSearchProvider());
    $event->search(new CategorySearchProvider());
    $event->search(new CommentSearchProvider());
}
```

Each provider returns results independently, grouped by category.

## Search UI

The global search is accessible via:

### Keyboard Shortcut

Press `Cmd+K` (Mac) or `Ctrl+K` (Windows/Linux) to open search from anywhere in the admin panel.

### Search Button

Click the search icon in the admin header.

### Direct URL

Navigate to `/admin/search?q=query`.

## Configuration

```php
// config/admin.php
'search' => [
    'enabled' => true,
    'min_length' => 2,          // Minimum query length
    'limit' => 10,              // Results per provider
    'debounce' => 300,          // Debounce delay (ms)
    'show_empty_results' => true,
    'shortcuts' => [
        'mac' => 'cmd+k',
        'windows' => 'ctrl+k',
    ],
],
```

## Search Suggestions

Provide autocomplete suggestions:

```php
public function getSuggestions(string $query): array
{
    return Post::where('title', 'like', "{$query}%")
        ->limit(5)
        ->pluck('title')
        ->toArray();
}
```

## Empty State

Customize empty search results:

```php
public function getEmptyMessage(string $query): string
{
    return "No posts found matching '{$query}'. Try a different search term.";
}

public function getEmptyActions(): array
{
    return [
        [
            'label' => 'Create New Post',
            'url' => route('admin.blog.posts.create'),
            'icon' => 'plus',
        ],
    ];
}
```

## Best Practices

### 1. Limit Results

```php
// ✅ Good - limit results
return Post::where('title', 'like', "%{$query}%")
    ->limit(5)
    ->get();

// ❌ Bad - return all results
return Post::where('title', 'like', "%{$query}%")->get();
```

### 2. Use Indexes

```php
// ✅ Good - indexed column
Schema::table('posts', function (Blueprint $table) {
    $table->index('title');
});
```

### 3. Search Multiple Fields

```php
// ✅ Good - comprehensive search
Post::where('title', 'like', "%{$query}%")
    ->orWhere('excerpt', 'like', "%{$query}%")
    ->orWhere('slug', 'like', "%{$query}%");
```

### 4. Include Context in Results

```php
// ✅ Good - helpful metadata
new SearchResult(
    title: $post->title,
    description: $post->excerpt,
    metadata: [
        'Author' => $post->author->name,
        'Date' => $post->created_at->format('M d, Y'),
    ]
);
```

### 5. Set Priority

```php
// ✅ Good - important resources first
public function getPriority(): int
{
    return 100; // Posts appear before comments
}
```

## Testing

```php
<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Mod\Blog\Models\Post;
use Mod\Blog\Search\PostSearchProvider;

class PostSearchTest extends TestCase
{
    public function test_searches_posts(): void
    {
        Post::factory()->create(['title' => 'Laravel Framework']);
        Post::factory()->create(['title' => 'Vue.js Guide']);

        $provider = new PostSearchProvider();
        $results = $provider->search('Laravel');

        $this->assertCount(1, $results);
        $this->assertEquals('Laravel Framework', $results[0]->title);
    }

    public function test_limits_results(): void
    {
        Post::factory()->count(10)->create([
            'title' => 'Test Post',
        ]);

        $provider = new PostSearchProvider();
        $results = $provider->search('Test');

        $this->assertLessThanOrEqual(5, count($results));
    }
}
```

## Learn More

- [Search Analytics →](/packages/core/search)
- [Admin Menus →](/packages/admin/menus)
- [Livewire Components →](/packages/admin/modals)
