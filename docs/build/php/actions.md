# Actions Pattern

Actions are single-purpose, reusable classes that encapsulate business logic. They provide a clean, testable alternative to fat controllers and model methods.

## Basic Action

```php
<?php

namespace Mod\Blog\Actions;

use Core\Actions\Action;
use Mod\Blog\Models\Post;

class CreatePost
{
    use Action;

    public function handle(array $data): Post
    {
        $post = Post::create($data);

        event(new PostCreated($post));

        return $post;
    }
}

// Usage
$post = CreatePost::run(['title' => 'My Post', 'content' => '...']);
```

## With Validation

```php
use Illuminate\Support\Facades\Validator;

class CreatePost
{
    use Action;

    public function handle(array $data): Post
    {
        $validated = Validator::make($data, [
            'title' => 'required|max:255',
            'content' => 'required',
            'status' => 'required|in:draft,published',
        ])->validate();

        return Post::create($validated);
    }
}
```

## With Authorization

```php
class DeletePost
{
    use Action;

    public function handle(Post $post, User $user): bool
    {
        if (!$user->can('delete', $post)) {
            throw new UnauthorizedException('Cannot delete this post');
        }

        $post->delete();

        return true;
    }
}

// Usage
DeletePost::run($post, auth()->user());
```

## With Events

```php
class PublishPost
{
    use Action;

    public function handle(Post $post): Post
    {
        $post->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        event(new PostPublished($post));

        return $post;
    }
}
```

## As Job

```php
class CreatePost
{
    use Action;

    public function asJob(): bool
    {
        return true; // Run as queued job
    }

    public function handle(array $data): Post
    {
        // Heavy processing...
        return Post::create($data);
    }
}

// Automatically queued
CreatePost::run($data);
```

## Best Practices

### 1. Single Responsibility
```php
// ✅ Good - one action, one purpose
CreatePost::run($data);
UpdatePost::run($post, $data);
DeletePost::run($post);

// ❌ Bad - multiple responsibilities
ManagePost::run($action, $post, $data);
```

### 2. Type Hints
```php
// ✅ Good - clear types
public function handle(Post $post, User $user): bool

// ❌ Bad - no types
public function handle($post, $user)
```

### 3. Descriptive Names
```php
// ✅ Good
PublishScheduledPosts
SendWeeklyNewsletter
GenerateMonthlyReport

// ❌ Bad
ProcessPosts
DoWork
HandleIt
```

## Testing

```php
use Tests\TestCase;
use Mod\Blog\Actions\CreatePost;

class CreatePostTest extends TestCase
{
    public function test_creates_post(): void
    {
        $post = CreatePost::run([
            'title' => 'Test Post',
            'content' => 'Content',
        ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
        ]);
    }
}
```

## Learn More
- [Lifecycle Events →](/core/events)
- [Module System →](/core/modules)
