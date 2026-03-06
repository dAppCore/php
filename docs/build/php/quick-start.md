# Quick Start

This tutorial walks you through creating your first module with Core PHP Framework. We'll build a simple blog module with posts, categories, and a public-facing website.

## Prerequisites

- Core PHP Framework installed ([Installation Guide](/guide/installation))
- Database configured
- Basic Laravel knowledge

## Step 1: Create the Module

Use the Artisan command to scaffold a new module:

```bash
php artisan make:mod Blog
```

This creates the following structure:

```
app/Mod/Blog/
├── Boot.php              # Module entry point
├── Actions/              # Business logic
├── Models/               # Eloquent models
├── Routes/
│   ├── web.php          # Public routes
│   ├── admin.php        # Admin routes
│   └── api.php          # API routes
├── Views/               # Blade templates
├── Migrations/          # Database migrations
├── Database/
│   ├── Factories/       # Model factories
│   └── Seeders/         # Database seeders
└── config.php           # Module configuration
```

## Step 2: Define Lifecycle Events

Open `app/Mod/Blog/Boot.php` and declare which events your module listens to:

```php
<?php

namespace Mod\Blog;

use Core\Events\WebRoutesRegistering;
use Core\Events\AdminPanelBooting;
use Core\Events\ApiRoutesRegistering;

class Boot
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
        AdminPanelBooting::class => 'onAdmin',
        ApiRoutesRegistering::class => 'onApiRoutes',
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->views('blog', __DIR__.'/Views');
        $event->routes(fn () => require __DIR__.'/Routes/web.php');
    }

    public function onAdmin(AdminPanelBooting $event): void
    {
        $event->routes(fn () => require __DIR__.'/Routes/admin.php');
        $event->menu(new BlogMenuProvider());
    }

    public function onApiRoutes(ApiRoutesRegistering $event): void
    {
        $event->routes(fn () => require __DIR__.'/Routes/api.php');
    }
}
```

## Step 3: Create Models

Create a `Post` model at `app/Mod/Blog/Models/Post.php`:

```php
<?php

namespace Mod\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Core\Mod\Tenant\Concerns\BelongsToWorkspace;
use Core\Activity\Concerns\LogsActivity;

class Post extends Model
{
    use BelongsToWorkspace, SoftDeletes, LogsActivity;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'published_at',
        'category_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    // Activity log configuration
    protected array $activityLogAttributes = ['title', 'published_at'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
```

## Step 4: Create Migration

Create a migration at `app/Mod/Blog/Migrations/2026_01_01_000001_create_blog_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['workspace_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};
```

Run the migration:

```bash
php artisan migrate
```

## Step 5: Create Actions

Create a `CreatePost` action at `app/Mod/Blog/Actions/CreatePost.php`:

```php
<?php

namespace Mod\Blog\Actions;

use Core\Actions\Action;
use Mod\Blog\Models\Post;
use Illuminate\Support\Str;

class CreatePost
{
    use Action;

    public function handle(array $data): Post
    {
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        // Auto-generate excerpt if not provided
        if (empty($data['excerpt'])) {
            $data['excerpt'] = Str::limit(strip_tags($data['content']), 160);
        }

        return Post::create($data);
    }
}
```

Create an `UpdatePost` action at `app/Mod/Blog/Actions/UpdatePost.php`:

```php
<?php

namespace Mod\Blog\Actions;

use Core\Actions\Action;
use Mod\Blog\Models\Post;

class UpdatePost
{
    use Action;

    public function handle(Post $post, array $data): Post
    {
        $post->update($data);

        return $post->fresh();
    }
}
```

## Step 6: Create Routes

Define web routes in `app/Mod/Blog/Routes/web.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Mod\Blog\Controllers\BlogController;

Route::name('blog.')->group(function () {
    Route::get('/blog', [BlogController::class, 'index'])->name('index');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('show');
    Route::get('/blog/category/{slug}', [BlogController::class, 'category'])->name('category');
});
```

Define admin routes in `app/Mod/Blog/Routes/admin.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Mod\Blog\Controllers\Admin\PostController;
use Mod\Blog\Controllers\Admin\CategoryController;

Route::prefix('blog')->name('admin.blog.')->group(function () {
    Route::resource('posts', PostController::class);
    Route::resource('categories', CategoryController::class);

    Route::post('posts/{post}/publish', [PostController::class, 'publish'])
        ->name('posts.publish');
});
```

## Step 7: Create Controllers

Create a web controller at `app/Mod/Blog/Controllers/BlogController.php`:

```php
<?php

namespace Mod\Blog\Controllers;

use Mod\Blog\Models\Post;
use Mod\Blog\Models\Category;
use Illuminate\Http\Request;

class BlogController
{
    public function index()
    {
        $posts = Post::with('category')
            ->published()
            ->latest('published_at')
            ->paginate(12);

        return view('blog::index', compact('posts'));
    }

    public function show(string $slug)
    {
        $post = Post::with('category')
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        return view('blog::show', compact('post'));
    }

    public function category(string $slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $posts = Post::with('category')
            ->where('category_id', $category->id)
            ->published()
            ->latest('published_at')
            ->paginate(12);

        return view('blog::category', compact('category', 'posts'));
    }
}
```

Create an admin controller at `app/Mod/Blog/Controllers/Admin/PostController.php`:

```php
<?php

namespace Mod\Blog\Controllers\Admin;

use Mod\Blog\Models\Post;
use Mod\Blog\Actions\CreatePost;
use Mod\Blog\Actions\UpdatePost;
use Mod\Blog\Requests\StorePostRequest;
use Mod\Blog\Requests\UpdatePostRequest;

class PostController
{
    public function index()
    {
        return view('blog::admin.posts.index');
    }

    public function create()
    {
        return view('blog::admin.posts.create');
    }

    public function store(StorePostRequest $request)
    {
        $post = CreatePost::run($request->validated());

        return redirect()
            ->route('admin.blog.posts.edit', $post)
            ->with('success', 'Post created successfully');
    }

    public function edit(Post $post)
    {
        return view('blog::admin.posts.edit', compact('post'));
    }

    public function update(UpdatePostRequest $request, Post $post)
    {
        UpdatePost::run($post, $request->validated());

        return back()->with('success', 'Post updated successfully');
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return redirect()
            ->route('admin.blog.posts.index')
            ->with('success', 'Post deleted successfully');
    }

    public function publish(Post $post)
    {
        UpdatePost::run($post, [
            'published_at' => now(),
        ]);

        return back()->with('success', 'Post published successfully');
    }
}
```

## Step 8: Create Admin Menu

Create a menu provider at `app/Mod/Blog/BlogMenuProvider.php`:

```php
<?php

namespace Mod\Blog;

use Core\Front\Admin\Contracts\AdminMenuProvider;
use Core\Front\Admin\Support\MenuItemBuilder;

class BlogMenuProvider implements AdminMenuProvider
{
    public function register(): array
    {
        return [
            MenuItemBuilder::make('Blog')
                ->icon('newspaper')
                ->priority(30)
                ->children([
                    MenuItemBuilder::make('Posts')
                        ->route('admin.blog.posts.index')
                        ->icon('document-text'),

                    MenuItemBuilder::make('Categories')
                        ->route('admin.blog.categories.index')
                        ->icon('folder'),

                    MenuItemBuilder::make('New Post')
                        ->route('admin.blog.posts.create')
                        ->icon('plus-circle'),
                ])
                ->build(),
        ];
    }
}
```

## Step 9: Create Views

Create a blog index view at `app/Mod/Blog/Views/index.blade.php`:

```blade
@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-4xl font-bold mb-8">Blog</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($posts as $post)
            <article class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    @if($post->category)
                        <span class="text-sm text-blue-600 font-medium">
                            {{ $post->category->name }}
                        </span>
                    @endif

                    <h2 class="text-xl font-bold mt-2 mb-3">
                        <a href="{{ route('blog.show', $post->slug) }}"
                           class="hover:text-blue-600">
                            {{ $post->title }}
                        </a>
                    </h2>

                    <p class="text-gray-600 mb-4">{{ $post->excerpt }}</p>

                    <div class="flex items-center justify-between">
                        <time class="text-sm text-gray-500">
                            {{ $post->published_at->format('M d, Y') }}
                        </time>

                        <a href="{{ route('blog.show', $post->slug) }}"
                           class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            Read more →
                        </a>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <div class="mt-8">
        {{ $posts->links() }}
    </div>
</div>
@endsection
```

## Step 10: Create Seeder (Optional)

Create a seeder at `app/Mod/Blog/Database/Seeders/BlogSeeder.php`:

```php
<?php

namespace Mod\Blog\Database\Seeders;

use Illuminate\Database\Seeder;
use Mod\Blog\Models\Category;
use Mod\Blog\Models\Post;
use Core\Database\Seeders\Attributes\SeederPriority;

#[SeederPriority(50)]
class BlogSeeder extends Seeder
{
    public function run(): void
    {
        // Create categories
        $tech = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Technology news and articles',
        ]);

        $design = Category::create([
            'name' => 'Design',
            'slug' => 'design',
            'description' => 'Design tips and inspiration',
        ]);

        // Create posts
        Post::create([
            'category_id' => $tech->id,
            'title' => 'Getting Started with Core PHP',
            'slug' => 'getting-started-with-core-php',
            'excerpt' => 'Learn how to build modular Laravel applications.',
            'content' => '<p>Full article content here...</p>',
            'published_at' => now()->subDays(7),
        ]);

        Post::create([
            'category_id' => $design->id,
            'title' => 'Modern UI Design Patterns',
            'slug' => 'modern-ui-design-patterns',
            'excerpt' => 'Explore contemporary design patterns for web applications.',
            'content' => '<p>Full article content here...</p>',
            'published_at' => now()->subDays(3),
        ]);
    }
}
```

Run the seeder:

```bash
php artisan db:seed --class=Mod\\Blog\\Database\\Seeders\\BlogSeeder
```

Or use auto-discovery:

```bash
php artisan db:seed
```

## Step 11: Test Your Module

Visit your blog:

```
http://your-app.test/blog
```

Access the admin panel:

```
http://your-app.test/admin/blog/posts
```

## Next Steps

Now that you've created your first module, explore more advanced features:

### Add API Endpoints

Create API resources and controllers for programmatic access:

- [API Package Documentation](/packages/api)
- [OpenAPI Documentation](/packages/api#openapi-documentation)

### Add Activity Logging

Track changes to your posts:

- [Activity Logging Guide](/patterns-guide/activity-logging)

### Add Search Functionality

Integrate with the unified search system:

- [Search Integration](/patterns-guide/search)

### Add Workspace Caching

Optimize database queries with team-scoped caching:

- [Workspace Caching](/patterns-guide/multi-tenancy#workspace-caching)

### Add Tests

Create feature tests for your module:

```bash
php artisan make:test Mod/Blog/PostTest
```

Example test:

```php
<?php

namespace Tests\Feature\Mod\Blog;

use Tests\TestCase;
use Mod\Blog\Models\Post;
use Mod\Blog\Actions\CreatePost;

class PostTest extends TestCase
{
    public function test_can_create_post(): void
    {
        $post = CreatePost::run([
            'title' => 'Test Post',
            'content' => 'Test content',
        ]);

        $this->assertDatabaseHas('blog_posts', [
            'title' => 'Test Post',
            'slug' => 'test-post',
        ]);
    }

    public function test_published_posts_are_visible(): void
    {
        Post::factory()->create([
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get('/blog');

        $response->assertStatus(200);
    }
}
```

## Learn More

- [Architecture Overview](/architecture/lifecycle-events)
- [Actions Pattern](/patterns-guide/actions)
- [Multi-Tenancy Guide](/patterns-guide/multi-tenancy)
- [Admin Panel Customization](/packages/admin)
