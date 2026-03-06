# Service Pattern

Services encapsulate business logic and coordinate between multiple models or external systems.

## When to Use Services

Use services for:
- Complex business logic involving multiple models
- External API integrations
- Operations requiring multiple steps
- Reusable functionality across controllers

**Don't use services for:**
- Simple CRUD operations (use Actions)
- Single-model operations
- View logic (use View Models)

## Basic Service

```php
<?php

namespace Mod\Blog\Services;

use Mod\Blog\Models\Post;
use Mod\Tenant\Models\User;

class PostPublishingService
{
    public function publish(Post $post, User $user): Post
    {
        // Verify post is ready
        $this->validateReadyForPublish($post);

        // Update post
        $post->update([
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $user->id,
        ]);

        // Generate SEO metadata
        $this->generateSeoMetadata($post);

        // Notify subscribers
        $this->notifySubscribers($post);

        // Update search index
        $post->searchable();

        return $post->fresh();
    }

    protected function validateReadyForPublish(Post $post): void
    {
        if (empty($post->title)) {
            throw new ValidationException('Post must have a title');
        }

        if (empty($post->content)) {
            throw new ValidationException('Post must have content');
        }

        if (!$post->featured_image) {
            throw new ValidationException('Post must have a featured image');
        }
    }

    protected function generateSeoMetadata(Post $post): void
    {
        if (empty($post->meta_description)) {
            $post->meta_description = str($post->content)
                ->stripTags()
                ->limit(160);
        }

        if (empty($post->og_image)) {
            GenerateOgImageJob::dispatch($post);
        }

        $post->save();
    }

    protected function notifySubscribers(Post $post): void
    {
        NotifySubscribersJob::dispatch($post);
    }
}
```

**Usage:**

```php
$service = app(PostPublishingService::class);
$publishedPost = $service->publish($post, auth()->user());
```

## Service with Constructor Injection

```php
<?php

namespace Mod\Analytics\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AnalyticsService
{
    public function __construct(
        protected string $apiKey,
        protected string $apiUrl
    ) {}

    public function trackPageView(string $url, array $meta = []): void
    {
        Http::post("{$this->apiUrl}/events", [
            'api_key' => $this->apiKey,
            'event' => 'pageview',
            'url' => $url,
            'meta' => $meta,
        ]);
    }

    public function getPageViews(string $url, int $days = 30): int
    {
        return Cache::remember(
            "analytics.pageviews.{$url}.{$days}",
            now()->addHour(),
            fn () => Http::get("{$this->apiUrl}/stats", [
                'api_key' => $this->apiKey,
                'url' => $url,
                'days' => $days,
            ])->json('views')
        );
    }
}
```

**Service Provider:**

```php
$this->app->singleton(AnalyticsService::class, function () {
    return new AnalyticsService(
        apiKey: config('analytics.api_key'),
        apiUrl: config('analytics.api_url')
    );
});
```

## Service Contracts

Define interfaces for flexibility:

```php
<?php

namespace Core\Service\Contracts;

interface PaymentGatewayService
{
    public function charge(int $amount, string $currency, array $meta = []): PaymentResult;
    public function refund(string $transactionId, ?int $amount = null): RefundResult;
    public function getTransaction(string $transactionId): Transaction;
}
```

**Implementation:**

```php
<?php

namespace Mod\Stripe\Services;

use Core\Service\Contracts\PaymentGatewayService;

class StripePaymentService implements PaymentGatewayService
{
    public function __construct(
        protected \Stripe\StripeClient $client
    ) {}

    public function charge(int $amount, string $currency, array $meta = []): PaymentResult
    {
        $intent = $this->client->paymentIntents->create([
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => $meta,
        ]);

        return new PaymentResult(
            success: $intent->status === 'succeeded',
            transactionId: $intent->id,
            amount: $intent->amount,
            currency: $intent->currency
        );
    }

    // ... other methods
}
```

## Service with Dependencies

```php
<?php

namespace Mod\Shop\Services;

use Mod\Shop\Models\Order;
use Core\Service\Contracts\PaymentGatewayService;
use Mod\Email\Services\EmailService;

class OrderProcessingService
{
    public function __construct(
        protected PaymentGatewayService $payment,
        protected EmailService $email,
        protected InventoryService $inventory
    ) {}

    public function process(Order $order): ProcessingResult
    {
        // Validate inventory
        if (!$this->inventory->available($order->items)) {
            return ProcessingResult::failed('Insufficient inventory');
        }

        // Reserve inventory
        $this->inventory->reserve($order->items);

        try {
            // Charge payment
            $payment = $this->payment->charge(
                amount: $order->total,
                currency: $order->currency,
                meta: ['order_id' => $order->id]
            );

            if (!$payment->success) {
                $this->inventory->release($order->items);
                return ProcessingResult::failed('Payment failed');
            }

            // Update order
            $order->update([
                'status' => 'paid',
                'transaction_id' => $payment->transactionId,
                'paid_at' => now(),
            ]);

            // Send confirmation
            $this->email->send(
                to: $order->customer->email,
                template: 'order-confirmation',
                data: compact('order', 'payment')
            );

            return ProcessingResult::success($order);

        } catch (\Exception $e) {
            $this->inventory->release($order->items);
            throw $e;
        }
    }
}
```

## Service with Events

```php
<?php

namespace Mod\Blog\Services;

use Mod\Blog\Events\PostPublished;
use Mod\Blog\Events\PostScheduled;

class PostSchedulingService
{
    public function schedulePost(Post $post, Carbon $publishAt): void
    {
        $post->update([
            'status' => 'scheduled',
            'publish_at' => $publishAt,
        ]);

        // Dispatch event
        event(new PostScheduled($post, $publishAt));

        // Queue job to publish
        PublishScheduledPostJob::dispatch($post)
            ->delay($publishAt);
    }

    public function publishScheduledPost(Post $post): void
    {
        if ($post->status !== 'scheduled') {
            throw new InvalidStateException('Post is not scheduled');
        }

        $post->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        event(new PostPublished($post));
    }
}
```

## Testing Services

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mod\Blog\Services\PostPublishingService;
use Mod\Blog\Models\Post;

class PostPublishingServiceTest extends TestCase
{
    public function test_publishes_post(): void
    {
        $service = app(PostPublishingService::class);
        $user = User::factory()->create();
        $post = Post::factory()->create(['status' => 'draft']);

        $result = $service->publish($post, $user);

        $this->assertEquals('published', $result->status);
        $this->assertNotNull($result->published_at);
        $this->assertEquals($user->id, $result->published_by);
    }

    public function test_validates_post_before_publishing(): void
    {
        $service = app(PostPublishingService::class);
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'title' => '',
            'status' => 'draft',
        ]);

        $this->expectException(ValidationException::class);

        $service->publish($post, $user);
    }

    public function test_generates_seo_metadata(): void
    {
        $service = app(PostPublishingService::class);
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'content' => 'Long content here...',
            'meta_description' => null,
        ]);

        $result = $service->publish($post, $user);

        $this->assertNotNull($result->meta_description);
    }
}
```

## Best Practices

### 1. Single Responsibility

```php
// ✅ Good - focused service
class EmailVerificationService
{
    public function sendVerificationEmail(User $user): void {}
    public function verify(string $token): bool {}
    public function resend(User $user): void {}
}

// ❌ Bad - too broad
class UserService
{
    public function create() {}
    public function sendEmail() {}
    public function processPayment() {}
    public function generateReport() {}
}
```

### 2. Dependency Injection

```php
// ✅ Good - injected dependencies
public function __construct(
    protected EmailService $email,
    protected PaymentGateway $payment
) {}

// ❌ Bad - hard-coded dependencies
public function __construct()
{
    $this->email = new EmailService();
    $this->payment = new StripeGateway();
}
```

### 3. Return Types

```php
// ✅ Good - explicit return type
public function process(Order $order): ProcessingResult
{
    return new ProcessingResult(...);
}

// ❌ Bad - no return type
public function process(Order $order)
{
    return [...];
}
```

### 4. Error Handling

```php
// ✅ Good - handle errors gracefully
public function process(Order $order): ProcessingResult
{
    try {
        $result = $this->payment->charge($order->total);

        return ProcessingResult::success($result);
    } catch (PaymentException $e) {
        Log::error('Payment failed', ['order' => $order->id, 'error' => $e->getMessage()]);

        return ProcessingResult::failed($e->getMessage());
    }
}
```

## Learn More

- [Actions Pattern →](/patterns-guide/actions)
- [Repository Pattern →](/patterns-guide/repositories)
