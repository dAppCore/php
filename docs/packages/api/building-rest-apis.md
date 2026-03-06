# Building REST APIs

This guide covers how to build production-ready REST APIs using the core-api package. You'll learn to create resources, implement pagination, add filtering and sorting, and secure endpoints with authentication.

## Quick Start

Register API routes by listening to the `ApiRoutesRegistering` event:

```php
<?php

namespace Mod\Blog;

use Core\Events\ApiRoutesRegistering;

class Boot
{
    public static array $listens = [
        ApiRoutesRegistering::class => 'onApiRoutes',
    ];

    public function onApiRoutes(ApiRoutesRegistering $event): void
    {
        $event->routes(function () {
            Route::apiResource('posts', Api\PostController::class);
        });
    }
}
```

## Creating Resources

### API Resources

Transform Eloquent models into consistent JSON responses using Laravel's API Resources:

```php
<?php

namespace Mod\Blog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'post',
            'attributes' => [
                'title' => $this->title,
                'slug' => $this->slug,
                'excerpt' => $this->excerpt,
                'content' => $this->when(
                    $request->user()?->tokenCan('posts:read-content'),
                    $this->content
                ),
                'status' => $this->status,
                'published_at' => $this->published_at?->toIso8601String(),
            ],
            'relationships' => [
                'author' => $this->whenLoaded('author', fn () => [
                    'id' => $this->author->id,
                    'name' => $this->author->name,
                ]),
                'categories' => $this->whenLoaded('categories', fn () =>
                    $this->categories->map(fn ($cat) => [
                        'id' => $cat->id,
                        'name' => $cat->name,
                    ])
                ),
            ],
            'meta' => [
                'created_at' => $this->created_at->toIso8601String(),
                'updated_at' => $this->updated_at->toIso8601String(),
            ],
        ];
    }
}
```

### Resource Controllers

Build controllers that use the `HasApiResponses` trait for consistent error handling:

```php
<?php

namespace Mod\Blog\Api;

use App\Http\Controllers\Controller;
use Core\Mod\Api\Concerns\HasApiResponses;
use Core\Mod\Api\Resources\PaginatedCollection;
use Illuminate\Http\Request;
use Mod\Blog\Models\Post;
use Mod\Blog\Resources\PostResource;

class PostController extends Controller
{
    use HasApiResponses;

    public function index(Request $request)
    {
        $posts = Post::query()
            ->with(['author', 'categories'])
            ->paginate($request->input('per_page', 25));

        return new PaginatedCollection($posts, PostResource::class);
    }

    public function show(Post $post)
    {
        $post->load(['author', 'categories']);

        return new PostResource($post);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'in:draft,published',
        ]);

        $post = Post::create($validated);

        return $this->createdResponse(
            new PostResource($post),
            'Post created successfully.'
        );
    }

    public function update(Request $request, Post $post)
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'content' => 'string',
            'status' => 'in:draft,published',
        ]);

        $post->update($validated);

        return new PostResource($post);
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return response()->json(null, 204);
    }
}
```

## Pagination

### Using PaginatedCollection

The `PaginatedCollection` class provides standardized pagination metadata:

```php
use Core\Mod\Api\Resources\PaginatedCollection;

public function index(Request $request)
{
    $posts = Post::paginate(
        $request->input('per_page', config('api.pagination.default_per_page', 25))
    );

    return new PaginatedCollection($posts, PostResource::class);
}
```

### Response Format

Paginated responses include comprehensive metadata:

```json
{
  "data": [
    {"id": 1, "type": "post", "attributes": {...}},
    {"id": 2, "type": "post", "attributes": {...}}
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 25,
    "to": 25,
    "total": 250
  },
  "links": {
    "first": "https://api.example.com/v1/posts?page=1",
    "last": "https://api.example.com/v1/posts?page=10",
    "prev": null,
    "next": "https://api.example.com/v1/posts?page=2"
  }
}
```

### Pagination Best Practices

**1. Limit Maximum Page Size**

```php
public function index(Request $request)
{
    $perPage = min(
        $request->input('per_page', 25),
        config('api.pagination.max_per_page', 100)
    );

    return new PaginatedCollection(
        Post::paginate($perPage),
        PostResource::class
    );
}
```

**2. Use Cursor Pagination for Large Datasets**

```php
public function index(Request $request)
{
    $posts = Post::orderBy('id')
        ->cursorPaginate($request->input('per_page', 25));

    return PostResource::collection($posts);
}
```

**3. Include Total Count Conditionally**

For very large tables, counting can be expensive:

```php
public function index(Request $request)
{
    $query = Post::query();

    // Only count if explicitly requested
    if ($request->boolean('include_total')) {
        return new PaginatedCollection(
            $query->paginate($request->input('per_page', 25)),
            PostResource::class
        );
    }

    // Use simple pagination (no total count)
    return PostResource::collection(
        $query->simplePaginate($request->input('per_page', 25))
    );
}
```

## Filtering

### Query Parameter Filters

Implement flexible filtering with query parameters:

```php
public function index(Request $request)
{
    $query = Post::query();

    // Status filter
    if ($status = $request->input('status')) {
        $query->where('status', $status);
    }

    // Date range filters
    if ($after = $request->input('created_after')) {
        $query->where('created_at', '>=', $after);
    }

    if ($before = $request->input('created_before')) {
        $query->where('created_at', '<=', $before);
    }

    // Author filter
    if ($authorId = $request->input('author_id')) {
        $query->where('author_id', $authorId);
    }

    // Full-text search
    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('content', 'like', "%{$search}%");
        });
    }

    return new PaginatedCollection(
        $query->paginate($request->input('per_page', 25)),
        PostResource::class
    );
}
```

### Filter Validation

Validate filter parameters to prevent errors:

```php
public function index(Request $request)
{
    $request->validate([
        'status' => 'in:draft,published,archived',
        'created_after' => 'date|before_or_equal:created_before',
        'created_before' => 'date',
        'author_id' => 'integer|exists:users,id',
        'per_page' => 'integer|min:1|max:100',
    ]);

    // Apply filters...
}
```

### Reusable Filter Traits

Create a trait for common filtering patterns:

```php
<?php

namespace Mod\Blog\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FiltersQueries
{
    protected function applyFilters(Builder $query, Request $request): Builder
    {
        // Date filters
        if ($after = $request->input('created_after')) {
            $query->where('created_at', '>=', $after);
        }

        if ($before = $request->input('created_before')) {
            $query->where('created_at', '<=', $before);
        }

        if ($updatedAfter = $request->input('updated_after')) {
            $query->where('updated_at', '>=', $updatedAfter);
        }

        // Status filter (if model has status)
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return $query;
    }
}
```

## Sorting

### Sort Parameter

Implement sorting with a `sort` query parameter:

```php
public function index(Request $request)
{
    $query = Post::query();

    // Parse sort parameter: -created_at,title
    $sortFields = $this->parseSortFields(
        $request->input('sort', '-created_at')
    );

    foreach ($sortFields as $field => $direction) {
        $query->orderBy($field, $direction);
    }

    return new PaginatedCollection(
        $query->paginate($request->input('per_page', 25)),
        PostResource::class
    );
}

protected function parseSortFields(string $sort): array
{
    $allowedFields = ['id', 'title', 'created_at', 'updated_at', 'published_at'];
    $fields = [];

    foreach (explode(',', $sort) as $field) {
        $direction = 'asc';

        if (str_starts_with($field, '-')) {
            $direction = 'desc';
            $field = substr($field, 1);
        }

        if (in_array($field, $allowedFields)) {
            $fields[$field] = $direction;
        }
    }

    return $fields ?: ['created_at' => 'desc'];
}
```

### Sort Validation

Validate sort fields against an allowlist:

```php
public function index(Request $request)
{
    $request->validate([
        'sort' => [
            'string',
            'regex:/^-?(id|title|created_at|updated_at)(,-?(id|title|created_at|updated_at))*$/',
        ],
    ]);

    // Apply sorting...
}
```

## Authentication

### Protecting Routes

Use the `auth:api` middleware to protect endpoints:

```php
// In your Boot class
$event->routes(function () {
    // Public routes (no authentication)
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{post}', [PostController::class, 'show']);

    // Protected routes (require authentication)
    Route::middleware('auth:api')->group(function () {
        Route::post('/posts', [PostController::class, 'store']);
        Route::put('/posts/{post}', [PostController::class, 'update']);
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);
    });
});
```

### Scope-Based Authorization

Enforce API key scopes on routes:

```php
Route::middleware(['auth:api', 'scope:posts:write'])
    ->post('/posts', [PostController::class, 'store']);

Route::middleware(['auth:api', 'scope:posts:delete'])
    ->delete('/posts/{post}', [PostController::class, 'destroy']);
```

### Checking Scopes in Controllers

Verify scopes programmatically for fine-grained control:

```php
public function update(Request $request, Post $post)
{
    // Check if user can update posts
    if (!$request->user()->tokenCan('posts:write')) {
        return $this->accessDeniedResponse('Insufficient permissions to update posts.');
    }

    // Check if user can publish (requires elevated scope)
    if ($request->input('status') === 'published') {
        if (!$request->user()->tokenCan('posts:publish')) {
            return $this->accessDeniedResponse('Insufficient permissions to publish posts.');
        }
    }

    $post->update($request->validated());

    return new PostResource($post);
}
```

### API Key Authentication Examples

**PHP with Guzzle:**

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://api.example.com/v1/',
    'headers' => [
        'Authorization' => 'Bearer ' . $apiKey,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ],
]);

// List posts
$response = $client->get('posts', [
    'query' => [
        'status' => 'published',
        'per_page' => 50,
        'sort' => '-published_at',
    ],
]);

$posts = json_decode($response->getBody(), true);

// Create a post
$response = $client->post('posts', [
    'json' => [
        'title' => 'New Post',
        'content' => 'Post content here...',
        'status' => 'draft',
    ],
]);

$newPost = json_decode($response->getBody(), true);
```

**JavaScript with Fetch:**

```javascript
const API_KEY = 'sk_live_abc123...';
const BASE_URL = 'https://api.example.com/v1';

async function listPosts(params = {}) {
  const query = new URLSearchParams(params).toString();

  const response = await fetch(`${BASE_URL}/posts?${query}`, {
    headers: {
      'Authorization': `Bearer ${API_KEY}`,
      'Accept': 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error(`API error: ${response.status}`);
  }

  return response.json();
}

async function createPost(data) {
  const response = await fetch(`${BASE_URL}/posts`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${API_KEY}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message || 'Failed to create post');
  }

  return response.json();
}

// Usage
const posts = await listPosts({ status: 'published', per_page: 25 });
const newPost = await createPost({ title: 'Hello', content: 'World' });
```

**Python with Requests:**

```python
import requests

API_KEY = 'sk_live_abc123...'
BASE_URL = 'https://api.example.com/v1'

headers = {
    'Authorization': f'Bearer {API_KEY}',
    'Accept': 'application/json',
    'Content-Type': 'application/json',
}

# List posts
response = requests.get(
    f'{BASE_URL}/posts',
    headers=headers,
    params={
        'status': 'published',
        'per_page': 50,
        'sort': '-published_at',
    }
)
response.raise_for_status()
posts = response.json()

# Create a post
response = requests.post(
    f'{BASE_URL}/posts',
    headers=headers,
    json={
        'title': 'New Post',
        'content': 'Post content here...',
        'status': 'draft',
    }
)
response.raise_for_status()
new_post = response.json()
```

## OpenAPI Documentation

### Document Endpoints

Use attributes to auto-generate OpenAPI documentation:

```php
use Core\Mod\Api\Documentation\Attributes\ApiTag;
use Core\Mod\Api\Documentation\Attributes\ApiParameter;
use Core\Mod\Api\Documentation\Attributes\ApiResponse;
use Core\Mod\Api\Documentation\Attributes\ApiSecurity;

#[ApiTag('Posts', 'Blog post management')]
#[ApiSecurity('api_key')]
class PostController extends Controller
{
    #[ApiParameter('page', 'query', 'integer', 'Page number', example: 1)]
    #[ApiParameter('per_page', 'query', 'integer', 'Items per page', example: 25)]
    #[ApiParameter('status', 'query', 'string', 'Filter by status', enum: ['draft', 'published'])]
    #[ApiParameter('sort', 'query', 'string', 'Sort fields (prefix with - for desc)', example: '-created_at')]
    #[ApiResponse(200, PostResource::class, 'List of posts', paginated: true)]
    public function index(Request $request)
    {
        // ...
    }

    #[ApiParameter('id', 'path', 'integer', 'Post ID', required: true)]
    #[ApiResponse(200, PostResource::class, 'Post details')]
    #[ApiResponse(404, null, 'Post not found')]
    public function show(Post $post)
    {
        // ...
    }

    #[ApiResponse(201, PostResource::class, 'Post created')]
    #[ApiResponse(422, null, 'Validation error')]
    public function store(Request $request)
    {
        // ...
    }
}
```

## Error Handling

### Consistent Error Responses

Use the `HasApiResponses` trait for consistent errors:

```php
use Core\Mod\Api\Concerns\HasApiResponses;

class PostController extends Controller
{
    use HasApiResponses;

    public function show($id)
    {
        $post = Post::find($id);

        if (!$post) {
            return $this->notFoundResponse('Post');
        }

        return new PostResource($post);
    }

    public function store(Request $request)
    {
        // Check entitlement limits
        if (!$this->canCreatePost($request->user())) {
            return $this->limitReachedResponse(
                'posts',
                'You have reached your post limit. Please upgrade your plan.'
            );
        }

        // Validation errors are handled automatically by Laravel
        $validated = $request->validate([...]);

        // ...
    }
}
```

### Error Response Format

All errors follow a consistent format:

```json
{
  "error": "not_found",
  "message": "Post not found."
}
```

```json
{
  "error": "validation_failed",
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."],
    "content": ["The content must be at least 100 characters."]
  }
}
```

```json
{
  "error": "feature_limit_reached",
  "message": "You have reached your post limit.",
  "feature": "posts",
  "upgrade_url": "https://example.com/upgrade"
}
```

## Best Practices

### 1. Use API Resources

Always transform models through resources:

```php
// Good - consistent response format
return new PostResource($post);

// Bad - exposes database schema
return response()->json($post);
```

### 2. Validate All Input

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'content' => 'required|string|min:100',
        'status' => 'in:draft,published',
        'published_at' => 'nullable|date|after:now',
    ]);

    // Use validated data only
    $post = Post::create($validated);
}
```

### 3. Eager Load Relationships

```php
// Good - single query with eager loading
$posts = Post::with(['author', 'categories'])->paginate();

// Bad - N+1 queries
$posts = Post::paginate();
foreach ($posts as $post) {
    echo $post->author->name; // Additional query per post
}
```

### 4. Use Route Model Binding

```php
// Good - automatic 404 if not found
public function show(Post $post)
{
    return new PostResource($post);
}

// Unnecessary - route model binding handles this
public function show($id)
{
    $post = Post::findOrFail($id);
    return new PostResource($post);
}
```

### 5. Scope Data by Workspace

```php
public function index(Request $request)
{
    $workspaceId = $request->user()->currentWorkspaceId();

    $posts = Post::where('workspace_id', $workspaceId)
        ->paginate();

    return new PaginatedCollection($posts, PostResource::class);
}
```

## Testing

### Feature Tests

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Mod\Api\Models\ApiKey;
use Mod\Blog\Models\Post;

class PostApiTest extends TestCase
{
    public function test_lists_posts(): void
    {
        $apiKey = ApiKey::factory()->create([
            'scopes' => ['posts:read'],
        ]);

        Post::factory()->count(5)->create();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$apiKey->plaintext_key}",
        ])->getJson('/api/v1/posts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'attributes'],
                ],
                'meta' => ['current_page', 'total'],
                'links',
            ]);
    }

    public function test_filters_posts_by_status(): void
    {
        $apiKey = ApiKey::factory()->create(['scopes' => ['posts:read']]);

        Post::factory()->create(['status' => 'draft']);
        Post::factory()->create(['status' => 'published']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$apiKey->plaintext_key}",
        ])->getJson('/api/v1/posts?status=published');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_creates_post_with_valid_scope(): void
    {
        $apiKey = ApiKey::factory()->create([
            'scopes' => ['posts:write'],
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$apiKey->plaintext_key}",
        ])->postJson('/api/v1/posts', [
            'title' => 'Test Post',
            'content' => 'Test content...',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.attributes.title', 'Test Post');
    }

    public function test_rejects_create_without_scope(): void
    {
        $apiKey = ApiKey::factory()->create([
            'scopes' => ['posts:read'], // No write scope
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$apiKey->plaintext_key}",
        ])->postJson('/api/v1/posts', [
            'title' => 'Test Post',
            'content' => 'Test content...',
        ]);

        $response->assertForbidden();
    }
}
```

## Learn More

- [Authentication](/packages/api/authentication) - API key management
- [Rate Limiting](/packages/api/rate-limiting) - Tier-based rate limits
- [Scopes](/packages/api/scopes) - Permission system
- [Webhooks](/packages/api/webhooks) - Event notifications
- [OpenAPI Documentation](/packages/api/documentation) - Auto-generated docs
