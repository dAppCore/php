# API Documentation

Automatically generate OpenAPI 3.0 documentation with Swagger UI, Scalar, and ReDoc viewers.

## Overview

The API package automatically generates OpenAPI documentation from your routes, controllers, and doc blocks.

**Features:**
- Automatic route discovery
- OpenAPI 3.0 spec generation
- Multiple documentation viewers
- Security scheme documentation
- Request/response examples
- Interactive API explorer

## Accessing Documentation

### Available Endpoints

```
/api/docs           - Swagger UI (default)
/api/docs/scalar    - Scalar viewer
/api/docs/redoc     - ReDoc viewer
/api/docs/openapi   - Raw OpenAPI JSON
```

### Protection

Documentation is protected in production:

```php
// config/api.php
return [
    'documentation' => [
        'enabled' => env('API_DOCS_ENABLED', !app()->isProduction()),
        'middleware' => ['auth', 'can:view-api-docs'],
    ],
];
```

## Attributes

### Hiding Endpoints

```php
use Mod\Api\Documentation\Attributes\ApiHidden;

#[ApiHidden]
class InternalController
{
    // Entire controller hidden from docs
}

class PostController
{
    #[ApiHidden]
    public function internalMethod()
    {
        // Single method hidden
    }
}
```

### Tagging Endpoints

```php
use Mod\Api\Documentation\Attributes\ApiTag;

#[ApiTag('Blog Posts')]
class PostController
{
    // All methods tagged with "Blog Posts"
}
```

### Documenting Parameters

```php
use Mod\Api\Documentation\Attributes\ApiParameter;

class PostController
{
    #[ApiParameter(
        name: 'status',
        in: 'query',
        description: 'Filter by post status',
        required: false,
        schema: ['type' => 'string', 'enum' => ['draft', 'published', 'archived']]
    )]
    #[ApiParameter(
        name: 'category',
        in: 'query',
        description: 'Filter by category ID',
        schema: ['type' => 'integer']
    )]
    public function index(Request $request)
    {
        // GET /posts?status=published&category=5
    }
}
```

### Documenting Responses

```php
use Mod\Api\Documentation\Attributes\ApiResponse;

class PostController
{
    #[ApiResponse(
        status: 200,
        description: 'Post created successfully',
        content: [
            'application/json' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                    ],
                ],
            ],
        ]
    )]
    #[ApiResponse(
        status: 422,
        description: 'Validation error'
    )]
    public function store(Request $request)
    {
        // ...
    }
}
```

### Security Requirements

```php
use Mod\Api\Documentation\Attributes\ApiSecurity;

#[ApiSecurity(['apiKey' => []])]
class PostController
{
    // Requires API key authentication
}

#[ApiSecurity(['bearerAuth' => ['posts:write']])]
public function store(Request $request)
{
    // Requires Bearer token with posts:write scope
}
```

## Configuration

```php
// config/api.php
return [
    'documentation' => [
        'enabled' => true,

        'info' => [
            'title' => 'Core PHP Framework API',
            'description' => 'REST API for Core PHP Framework',
            'version' => '1.0.0',
            'contact' => [
                'name' => 'API Support',
                'email' => 'api@example.com',
                'url' => 'https://example.com/support',
            ],
        ],

        'servers' => [
            [
                'url' => 'https://api.example.com',
                'description' => 'Production',
            ],
            [
                'url' => 'https://staging.example.com',
                'description' => 'Staging',
            ],
        ],

        'security_schemes' => [
            'apiKey' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'API Key',
                'description' => 'API key authentication. Format: `Bearer sk_live_...`',
            ],
        ],

        'viewers' => [
            'swagger' => true,
            'scalar' => true,
            'redoc' => true,
        ],
    ],
];
```

## Extensions

### Custom Extensions

```php
<?php

namespace Mod\Blog\Api\Documentation;

use Mod\Api\Documentation\Extension;

class BlogExtension extends Extension
{
    public function apply(array $spec): array
    {
        // Add custom tags
        $spec['tags'][] = [
            'name' => 'Blog Posts',
            'description' => 'Operations for managing blog posts',
        ];

        // Add custom security requirements
        $spec['paths']['/posts']['post']['security'][] = [
            'apiKey' => [],
        ];

        return $spec;
    }
}
```

**Register Extension:**

```php
use Core\Events\ApiRoutesRegistering;

public function onApiRoutes(ApiRoutesRegistering $event): void
{
    $event->documentationExtension(new BlogExtension());
}
```

### Built-in Extensions

**Rate Limit Extension:**

```php
use Mod\Api\Documentation\Extensions\RateLimitExtension;

// Automatically documents rate limits in responses
// Adds X-RateLimit-* headers to all endpoints
```

**Workspace Header Extension:**

```php
use Mod\Api\Documentation\Extensions\WorkspaceHeaderExtension;

// Documents X-Workspace-ID header requirement
// Adds to all workspace-scoped endpoints
```

## Common Examples

### Pagination

```php
use Mod\Api\Documentation\Examples\CommonExamples;

#[ApiResponse(
    status: 200,
    description: 'Paginated list of posts',
    content: CommonExamples::paginatedResponse('posts', [
        'id' => 1,
        'title' => 'Example Post',
        'status' => 'published',
    ])
)]
public function index(Request $request)
{
    return PostResource::collection(
        Post::paginate(20)
    );
}
```

**Generates:**

```json
{
  "data": [
    {
      "id": 1,
      "title": "Example Post",
      "status": "published"
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "total": 100
  }
}
```

### Error Responses

```php
#[ApiResponse(
    status: 404,
    description: 'Post not found',
    content: CommonExamples::errorResponse('Post not found', 'resource_not_found')
)]
public function show(Post $post)
{
    return new PostResource($post);
}
```

## Module Discovery

The documentation system automatically discovers API routes from all modules:

```php
// Mod\Blog\Boot
public function onApiRoutes(ApiRoutesRegistering $event): void
{
    $event->routes(function () {
        Route::get('/posts', [PostController::class, 'index']);
        // Automatically included in docs
    });
}
```

**Discovery Process:**
1. Scan all registered API routes
2. Extract controller methods
3. Parse doc blocks and attributes
4. Generate OpenAPI spec
5. Cache for performance

## Viewers

### Swagger UI

Interactive API explorer with "Try it out" functionality.

**Access:** `/api/docs`

**Features:**
- Test endpoints directly
- View request/response examples
- OAuth/API key authentication
- Model schemas

### Scalar

Modern, clean documentation viewer.

**Access:** `/api/docs/scalar`

**Features:**
- Beautiful UI
- Dark mode
- Code examples in multiple languages
- Interactive examples

### ReDoc

Professional documentation with three-panel layout.

**Access:** `/api/docs/redoc`

**Features:**
- Search functionality
- Menu navigation
- Responsive design
- Printable

## Best Practices

### 1. Document All Public Endpoints

```php
// ✅ Good - documented
#[ApiTag('Posts')]
#[ApiResponse(200, 'Success')]
#[ApiResponse(422, 'Validation error')]
public function store(Request $request)

// ❌ Bad - undocumented
public function store(Request $request)
```

### 2. Provide Examples

```php
// ✅ Good - request example
#[ApiParameter(
    name: 'status',
    example: 'published'
)]

// ❌ Bad - no example
#[ApiParameter(name: 'status')]
```

### 3. Hide Internal Endpoints

```php
// ✅ Good - hidden
#[ApiHidden]
public function internal()

// ❌ Bad - exposed in docs
public function internal()
```

### 4. Group Related Endpoints

```php
// ✅ Good - tagged
#[ApiTag('Blog Posts')]
class PostController

// ❌ Bad - ungrouped
class PostController
```

## Testing

```php
use Tests\TestCase;

class DocumentationTest extends TestCase
{
    public function test_generates_openapi_spec(): void
    {
        $response = $this->getJson('/api/docs/openapi');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'openapi',
            'info',
            'paths',
            'components',
        ]);
    }

    public function test_includes_blog_endpoints(): void
    {
        $response = $this->getJson('/api/docs/openapi');

        $spec = $response->json();

        $this->assertArrayHasKey('/posts', $spec['paths']);
        $this->assertArrayHasKey('/posts/{id}', $spec['paths']);
    }
}
```

## Learn More

- [Authentication →](/packages/api/authentication)
- [Scopes →](/packages/api/scopes)
- [API Reference →](/api/endpoints)
