# Unified Search

Powerful cross-model search with analytics, suggestions, and highlighting.

## Basic Usage

### Setting Up Search

```php
<?php

namespace Mod\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Post extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'content' => strip_tags($this->content),
            'category' => $this->category->name,
            'tags' => $this->tags->pluck('name')->join(', '),
            'author' => $this->author->name,
        ];
    }
}
```

### Searching

```php
use Mod\Blog\Models\Post;

// Simple search
$results = Post::search('laravel tutorial')->get();

// Paginated search
$results = Post::search('php')
    ->paginate(20);

// With constraints
$results = Post::search('api')
    ->where('status', 'published')
    ->where('category_id', 5)
    ->get();
```

## Unified Search

Search across multiple models:

```php
use Core\Search\Unified;

$search = app(Unified::class);

// Search everything
$results = $search->search('api documentation', [
    \Mod\Blog\Models\Post::class,
    \Mod\Docs\Models\Page::class,
    \Mod\Shop\Models\Product::class,
]);

// Returns grouped results
[
    'posts' => [...],
    'pages' => [...],
    'products' => [...],
]
```

### Weighted Results

```php
// Boost specific models
$results = $search->search('tutorial', [
    \Mod\Blog\Models\Post::class => 2.0,     // 2x weight
    \Mod\Docs\Models\Page::class => 1.5,     // 1.5x weight
    \Mod\Video\Models\Video::class => 1.0,   // Normal weight
]);
```

### Result Limiting

```php
// Limit results per model
$results = $search->search('api', [
    \Mod\Blog\Models\Post::class,
    \Mod\Docs\Models\Page::class,
], perModel: 5); // Max 5 results per model
```

## Search Analytics

Track search queries and clicks:

```php
use Core\Search\Analytics\SearchAnalytics;

$analytics = app(SearchAnalytics::class);

// Record search
$analytics->recordSearch(
    query: 'laravel tutorial',
    results: 42,
    user: auth()->user()
);

// Record click-through
$analytics->recordClick(
    query: 'laravel tutorial',
    resultId: $post->id,
    resultType: Post::class,
    position: 3 // 3rd result clicked
);
```

### Analytics Queries

```php
// Popular searches
$popular = $analytics->popularSearches(limit: 10);

// Recent searches
$recent = $analytics->recentSearches(limit: 20);

// Zero-result searches (need attention!)
$empty = $analytics->emptySearches();

// Click-through rate
$ctr = $analytics->clickThroughRate('laravel tutorial');

// Average position of clicks
$avgPosition = $analytics->averageClickPosition('api docs');
```

### Search Dashboard

```php
use Core\Search\Analytics\SearchAnalytics;

class SearchDashboard extends Component
{
    public function render()
    {
        $analytics = app(SearchAnalytics::class);

        return view('search.dashboard', [
            'totalSearches' => $analytics->totalSearches(),
            'uniqueQueries' => $analytics->uniqueQueries(),
            'avgResultsPerSearch' => $analytics->averageResults(),
            'popularSearches' => $analytics->popularSearches(10),
            'emptySearches' => $analytics->emptySearches(),
        ]);
    }
}
```

## Search Suggestions

Autocomplete and query suggestions:

```php
use Core\Search\Suggestions\SearchSuggestions;

$suggestions = app(SearchSuggestions::class);

// Get suggestions for partial query
$results = $suggestions->suggest('lar', [
    \Mod\Blog\Models\Post::class,
]);

// Returns:
[
    'laravel',
    'laravel tutorial',
    'laravel api',
    'laravel testing',
]
```

### Configuration

```php
// config/search.php
return [
    'suggestions' => [
        'enabled' => true,
        'min_length' => 2,        // Minimum query length
        'max_results' => 10,       // Max suggestions
        'cache_ttl' => 3600,       // Cache for 1 hour
        'learn_from_searches' => true, // Build from analytics
    ],
];
```

### Livewire Autocomplete

```php
class SearchBox extends Component
{
    public $query = '';
    public $suggestions = [];

    public function updatedQuery()
    {
        if (strlen($this->query) < 2) {
            $this->suggestions = [];
            return;
        }

        $suggestions = app(SearchSuggestions::class);
        $this->suggestions = $suggestions->suggest($this->query, [
            Post::class,
            Page::class,
        ]);
    }

    public function render()
    {
        return view('livewire.search-box');
    }
}
```

```blade
<div>
    <input
        type="search"
        wire:model.live.debounce.300ms="query"
        placeholder="Search..."
    >

    @if(count($suggestions) > 0)
        <ul class="suggestions">
            @foreach($suggestions as $suggestion)
                <li wire:click="$set('query', '{{ $suggestion }}')">
                    {{ $suggestion }}
                </li>
            @endforeach
        </ul>
    @endif
</div>
```

## Highlighting

Highlight matching terms in results:

```php
use Core\Search\Support\SearchHighlighter;

$highlighter = app(SearchHighlighter::class);

// Highlight text
$highlighted = $highlighter->highlight(
    text: $post->title,
    query: 'laravel tutorial',
    tag: 'mark'
);

// Returns: "Getting started with <mark>Laravel</mark> <mark>Tutorial</mark>"
```

### Configuration

```php
// config/search.php
return [
    'highlighting' => [
        'enabled' => true,
        'tag' => 'mark',           // HTML tag to use
        'class' => 'highlight',    // CSS class
        'max_length' => 200,       // Snippet length
        'context' => 50,           // Context around match
    ],
];
```

### Blade Component

```blade
<x-search-result :post="$post" :query="$query">
    <h3>{{ $post->title }}</h3>
    <p>{!! highlight($post->excerpt, $query) !!}</p>
</x-search-result>
```

**Helper Function:**

```php
// helpers.php
function highlight(string $text, string $query, string $tag = 'mark'): string
{
    return app(SearchHighlighter::class)->highlight($text, $query, $tag);
}
```

## Filtering & Faceting

### Adding Filters

```php
// Search with filters
$results = Post::search('tutorial')
    ->where('status', 'published')
    ->where('category_id', 5)
    ->where('created_at', '>=', now()->subDays(30))
    ->get();
```

### Faceted Search

```php
use Laravel\Scout\Builder;

// Get facet counts
$facets = Post::search('api')
    ->with('category')
    ->get()
    ->groupBy('category.name')
    ->map->count();

// Returns:
[
    'Tutorials' => 12,
    'Documentation' => 8,
    'News' => 5,
]
```

### Livewire Facets

```php
class FacetedSearch extends Component
{
    public $query = '';
    public $category = null;
    public $status = 'published';

    public function render()
    {
        $results = Post::search($this->query)
            ->when($this->category, fn($q) => $q->where('category_id', $this->category))
            ->where('status', $this->status)
            ->paginate(20);

        $facets = Post::search($this->query)
            ->where('status', $this->status)
            ->get()
            ->groupBy('category.name')
            ->map->count();

        return view('livewire.faceted-search', [
            'results' => $results,
            'facets' => $facets,
        ]);
    }
}
```

## Scout Drivers

### Meilisearch (Recommended)

```bash
# Install Meilisearch
brew install meilisearch

# Start server
meilisearch --master-key=YOUR_MASTER_KEY
```

**Configuration:**

```php
// config/scout.php
return [
    'driver' => 'meilisearch',

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
    ],
];
```

### Database Driver

For small applications:

```php
// config/scout.php
return [
    'driver' => 'database',
];
```

**Limitations:**
- No relevance scoring
- No typo tolerance
- Slower for large datasets
- Good for < 10,000 records

### Algolia

```php
// config/scout.php
return [
    'driver' => 'algolia',

    'algolia' => [
        'id' => env('ALGOLIA_APP_ID'),
        'secret' => env('ALGOLIA_SECRET'),
    ],
];
```

## Indexing

### Manual Indexing

```bash
# Index all records
php artisan scout:import "Mod\Blog\Models\Post"

# Flush index
php artisan scout:flush "Mod\Blog\Models\Post"

# Re-import
php artisan scout:flush "Mod\Blog\Models\Post"
php artisan scout:import "Mod\Blog\Models\Post"
```

### Conditional Indexing

```php
class Post extends Model
{
    use Searchable;

    public function shouldBeSearchable(): bool
    {
        return $this->status === 'published';
    }
}
```

### Batch Indexing

```php
// Automatically batched
Post::chunk(100, function ($posts) {
    $posts->searchable();
});
```

## Performance

### Eager Loading

```php
// ✅ Good - eager load relationships
$results = Post::search('tutorial')
    ->with(['category', 'author', 'tags'])
    ->get();

// ❌ Bad - N+1 queries
$results = Post::search('tutorial')->get();
foreach ($results as $post) {
    echo $post->category->name; // Query per post
}
```

### Result Caching

```php
use Illuminate\Support\Facades\Cache;

// Cache search results
$results = Cache::remember(
    "search:{$query}:{$page}",
    now()->addMinutes(5),
    fn () => Post::search($query)->paginate(20)
);
```

### Query Throttling

```php
// Rate limit search endpoint
Route::middleware('throttle:60,1')
    ->get('/search', [SearchController::class, 'index']);
```

## Best Practices

### 1. Index Only What's Needed

```php
// ✅ Good - essential fields only
public function toSearchableArray(): array
{
    return [
        'title' => $this->title,
        'content' => strip_tags($this->content),
    ];
}

// ❌ Bad - too much data
public function toSearchableArray(): array
{
    return $this->toArray(); // Includes everything!
}
```

### 2. Use Conditional Indexing

```php
// ✅ Good - index published only
public function shouldBeSearchable(): bool
{
    return $this->status === 'published';
}

// ❌ Bad - index drafts
public function shouldBeSearchable(): bool
{
    return true;
}
```

### 3. Track Analytics

```php
// ✅ Good - record searches
$analytics->recordSearch($query, $results->count());

// Use analytics to improve search
$emptySearches = $analytics->emptySearches();
// Add synonyms, fix typos, expand content
```

### 4. Provide Suggestions

```php
// ✅ Good - help users find content
<input wire:model.live.debounce.300ms="query">

@if($suggestions)
    <ul>
        @foreach($suggestions as $suggestion)
            <li>{{ $suggestion }}</li>
        @endforeach
    </ul>
@endif
```

## Testing

```php
use Tests\TestCase;
use Mod\Blog\Models\Post;

class SearchTest extends TestCase
{
    public function test_searches_posts(): void
    {
        Post::factory()->create(['title' => 'Laravel Tutorial']);
        Post::factory()->create(['title' => 'PHP Basics']);

        $results = Post::search('laravel')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Laravel Tutorial', $results->first()->title);
    }

    public function test_filters_results(): void
    {
        Post::factory()->create([
            'title' => 'Laravel Tutorial',
            'status' => 'published',
        ]);

        Post::factory()->create([
            'title' => 'Laravel Guide',
            'status' => 'draft',
        ]);

        $results = Post::search('laravel')
            ->where('status', 'published')
            ->get();

        $this->assertCount(1, $results);
    }
}
```

## Learn More

- [Configuration →](/core/configuration)
- [Global Search →](/packages/admin/search)
