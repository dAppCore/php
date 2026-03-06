# Testing Guide

Comprehensive guide to testing Core PHP Framework applications.

## Running Tests

```bash
# Run all tests
composer test

# Run specific test file
./vendor/bin/phpunit packages/core-php/tests/Feature/ActivityLogServiceTest.php

# Run tests with coverage
./vendor/bin/phpunit --coverage-html coverage

# Run specific test method
./vendor/bin/phpunit --filter test_creates_post
```

## Test Structure

```
tests/
├── Feature/              # Integration tests
│   ├── ApiTest.php
│   ├── AuthTest.php
│   └── PostTest.php
├── Unit/                 # Unit tests
│   ├── ActionTest.php
│   └── ServiceTest.php
└── TestCase.php          # Base test case
```

## Writing Feature Tests

Feature tests test complete workflows:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Mod\Blog\Models\Post;
use Mod\Tenant\Models\User;

class PostTest extends TestCase
{
    public function test_user_can_create_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/posts', [
                'title' => 'Test Post',
                'content' => 'Test content',
                'status' => 'draft',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'author_id' => $user->id,
        ]);
    }

    public function test_guest_cannot_create_post(): void
    {
        $response = $this->post('/posts', [
            'title' => 'Test Post',
            'content' => 'Test content',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_view_own_posts(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get("/posts/{$post->id}");

        $response->assertOk();
        $response->assertSee($post->title);
    }
}
```

## Writing Unit Tests

Unit tests test isolated components:

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use Mod\Blog\Actions\CreatePost;
use Mod\Blog\Models\Post;

class CreatePostTest extends TestCase
{
    public function test_creates_post(): void
    {
        $post = CreatePost::run([
            'title' => 'Test Post',
            'content' => 'Test content',
            'status' => 'draft',
        ]);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertEquals('Test Post', $post->title);
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_generates_slug_from_title(): void
    {
        $post = CreatePost::run([
            'title' => 'Test Post',
            'content' => 'Content',
        ]);

        $this->assertEquals('test-post', $post->slug);
    }

    public function test_throws_exception_for_invalid_data(): void
    {
        $this->expectException(ValidationException::class);

        CreatePost::run([
            'title' => '', // Invalid
            'content' => 'Content',
        ]);
    }
}
```

## Database Testing

### Factories

```php
<?php

namespace Mod\Blog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(3, true),
            'status' => 'draft',
            'author_id' => User::factory(),
        ];
    }

    public function published(): self
    {
        return $this->state([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function draft(): self
    {
        return $this->state(['status' => 'draft']);
    }
}
```

**Usage:**

```php
// Create single post
$post = Post::factory()->create();

// Create published post
$post = Post::factory()->published()->create();

// Create multiple posts
$posts = Post::factory()->count(10)->create();

// Create with specific attributes
$post = Post::factory()->create([
    'title' => 'Specific Title',
]);
```

### Database Assertions

```php
// Assert record exists
$this->assertDatabaseHas('posts', [
    'title' => 'Test Post',
    'status' => 'published',
]);

// Assert record doesn't exist
$this->assertDatabaseMissing('posts', [
    'title' => 'Deleted Post',
]);

// Assert record count
$this->assertDatabaseCount('posts', 10);

// Assert model exists
$this->assertModelExists($post);

// Assert model deleted
$this->assertSoftDeleted($post);
```

## API Testing

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class PostApiTest extends TestCase
{
    public function test_lists_posts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['posts:read']);

        Post::factory()->count(5)->published()->create();

        $response = $this->getJson('/api/v1/posts');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'status', 'created_at'],
            ],
        ]);
    }

    public function test_creates_post(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['posts:write']);

        $response = $this->postJson('/api/v1/posts', [
            'title' => 'API Test Post',
            'content' => 'Test content',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'title' => 'API Test Post',
        ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'API Test Post',
        ]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/posts');

        $response->assertUnauthorized();
    }

    public function test_requires_correct_scope(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['posts:read']); // Missing write scope

        $response = $this->postJson('/api/v1/posts', [
            'title' => 'Test',
            'content' => 'Content',
        ]);

        $response->assertForbidden();
    }
}
```

## Livewire Testing

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use Mod\Blog\View\Modal\Admin\PostEditor;

class PostEditorTest extends TestCase
{
    public function test_renders_post_editor(): void
    {
        $post = Post::factory()->create();

        Livewire::test(PostEditor::class, ['post' => $post])
            ->assertSee($post->title)
            ->assertSee('Save');
    }

    public function test_updates_post(): void
    {
        $post = Post::factory()->create(['title' => 'Original']);

        Livewire::test(PostEditor::class, ['post' => $post])
            ->set('title', 'Updated Title')
            ->call('save')
            ->assertDispatched('post-updated');

        $this->assertEquals('Updated Title', $post->fresh()->title);
    }

    public function test_validates_input(): void
    {
        $post = Post::factory()->create();

        Livewire::test(PostEditor::class, ['post' => $post])
            ->set('title', '')
            ->call('save')
            ->assertHasErrors(['title' => 'required']);
    }
}
```

## Mocking

### Mocking Services

```php
use Mockery;
use Mod\Payment\Services\PaymentService;

public function test_processes_order_with_mock(): void
{
    $mock = Mockery::mock(PaymentService::class);
    $mock->shouldReceive('charge')
        ->once()
        ->with(1000, 'GBP')
        ->andReturn(new PaymentResult(success: true));

    $this->app->instance(PaymentService::class, $mock);

    $order = Order::factory()->create();
    $result = $this->orderService->process($order);

    $this->assertTrue($result->success);
}
```

### Mocking Facades

```php
use Illuminate\Support\Facades\Storage;

public function test_uploads_file(): void
{
    Storage::fake('s3');

    $this->post('/upload', [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ]);

    Storage::disk('s3')->assertExists('photos/photo.jpg');
}
```

### Mocking Events

```php
use Illuminate\Support\Facades\Event;
use Mod\Blog\Events\PostPublished;

public function test_fires_event(): void
{
    Event::fake([PostPublished::class]);

    $post = Post::factory()->create();
    $service->publish($post);

    Event::assertDispatched(PostPublished::class, function ($event) use ($post) {
        return $event->post->id === $post->id;
    });
}
```

## Testing Workspace Isolation

```php
public function test_scopes_to_workspace(): void
{
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();

    $post1 = Post::factory()->create(['workspace_id' => $workspace1->id]);
    $post2 = Post::factory()->create(['workspace_id' => $workspace2->id]);

    // Acting as user in workspace1
    $user = User::factory()->create(['workspace_id' => $workspace1->id]);

    $posts = Post::all(); // Should only see workspace1's posts

    $this->assertCount(1, $posts);
    $this->assertEquals($post1->id, $posts->first()->id);
}
```

## Best Practices

### 1. Test One Thing

```php
// ✅ Good - tests one behavior
public function test_creates_post(): void
{
    $post = CreatePost::run([...]);
    $this->assertInstanceOf(Post::class, $post);
}

// ❌ Bad - tests multiple things
public function test_post_operations(): void
{
    $post = CreatePost::run([...]);
    $this->assertInstanceOf(Post::class, $post);

    $post->publish();
    $this->assertEquals('published', $post->status);

    $post->delete();
    $this->assertSoftDeleted($post);
}
```

### 2. Use Descriptive Names

```php
// ✅ Good
public function test_user_can_create_post_with_valid_data(): void

// ❌ Bad
public function test_create(): void
```

### 3. Arrange, Act, Assert

```php
public function test_publishes_post(): void
{
    // Arrange
    $post = Post::factory()->create(['status' => 'draft']);
    $user = User::factory()->create();

    // Act
    $result = $service->publish($post, $user);

    // Assert
    $this->assertEquals('published', $result->status);
    $this->assertNotNull($result->published_at);
}
```

### 4. Clean Up After Tests

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class PostTest extends TestCase
{
    use RefreshDatabase; // Resets database after each test

    public function test_something(): void
    {
        // Test code
    }
}
```

## Learn More

- [Actions Pattern →](/patterns-guide/actions)
- [Service Pattern →](/patterns-guide/services)
- [Contributing →](/contributing)
