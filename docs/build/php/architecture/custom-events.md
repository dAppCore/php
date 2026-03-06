# Creating Custom Events

Learn how to create custom lifecycle events for extensibility in your modules.

## Why Custom Events?

Custom lifecycle events allow you to:
- Create extension points in your modules
- Enable third-party integrations
- Decouple module components
- Follow the framework's event-driven pattern

## Basic Custom Event

### Step 1: Create Event Class

```php
<?php

namespace Mod\Shop\Events;

use Core\Events\LifecycleEvent;
use Core\Events\Concerns\HasEventVersion;

class PaymentGatewaysRegistering extends LifecycleEvent
{
    use HasEventVersion;

    protected array $gateways = [];

    public function gateway(string $name, string $class): void
    {
        $this->gateways[$name] = $class;
    }

    public function getGateways(): array
    {
        return $this->gateways;
    }

    public function version(): string
    {
        return '1.0.0';
    }
}
```

### Step 2: Fire Event

```php
<?php

namespace Mod\Shop;

use Core\Events\FrameworkBooted;
use Mod\Shop\Events\PaymentGatewaysRegistering;

class Boot
{
    public static array $listens = [
        FrameworkBooted::class => 'onFrameworkBooted',
    ];

    public function onFrameworkBooted(FrameworkBooted $event): void
    {
        // Fire custom event
        $gatewayEvent = new PaymentGatewaysRegistering();
        event($gatewayEvent);

        // Register all collected gateways
        foreach ($gatewayEvent->getGateways() as $name => $class) {
            app('payment.gateways')->register($name, $class);
        }
    }
}
```

### Step 3: Listen to Event

```php
<?php

namespace Mod\Stripe;

use Mod\Shop\Events\PaymentGatewaysRegistering;

class Boot
{
    public static array $listens = [
        PaymentGatewaysRegistering::class => 'onPaymentGateways',
    ];

    public function onPaymentGateways(PaymentGatewaysRegistering $event): void
    {
        $event->gateway('stripe', StripeGateway::class);
    }
}
```

## Event with Multiple Methods

Provide different registration methods:

```php
<?php

namespace Mod\Blog\Events;

use Core\Events\LifecycleEvent;

class ContentTypesRegistering extends LifecycleEvent
{
    protected array $types = [];
    protected array $renderers = [];
    protected array $validators = [];

    public function type(string $name, string $model): void
    {
        $this->types[$name] = $model;
    }

    public function renderer(string $type, string $class): void
    {
        $this->renderers[$type] = $class;
    }

    public function validator(string $type, array $rules): void
    {
        $this->validators[$type] = $rules;
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function getRenderers(): array
    {
        return $this->renderers;
    }

    public function getValidators(): array
    {
        return $this->validators;
    }
}
```

**Usage:**

```php
public function onContentTypes(ContentTypesRegistering $event): void
{
    $event->type('video', Video::class);
    $event->renderer('video', VideoRenderer::class);
    $event->validator('video', [
        'url' => 'required|url',
        'duration' => 'required|integer',
    ]);
}
```

## Event with Configuration

Pass configuration to listeners:

```php
<?php

namespace Mod\Analytics\Events;

use Core\Events\LifecycleEvent;

class AnalyticsProvidersRegistering extends LifecycleEvent
{
    protected array $providers = [];

    public function __construct(
        public readonly array $config
    ) {}

    public function provider(string $name, string $class, array $config = []): void
    {
        $this->providers[$name] = [
            'class' => $class,
            'config' => array_merge($this->config[$name] ?? [], $config),
        ];
    }

    public function getProviders(): array
    {
        return $this->providers;
    }
}
```

**Fire with Config:**

```php
$event = new AnalyticsProvidersRegistering(
    config('analytics.providers')
);
event($event);
```

## Event Versioning

Track event versions for backward compatibility:

```php
<?php

namespace Mod\Api\Events;

use Core\Events\LifecycleEvent;
use Core\Events\Concerns\HasEventVersion;

class ApiEndpointsRegistering extends LifecycleEvent
{
    use HasEventVersion;

    public function version(): string
    {
        return '2.0.0';
    }

    // v2 method
    public function endpoint(string $path, string $controller, array $options = []): void
    {
        $this->endpoints[] = compact('path', 'controller', 'options');
    }

    // v1 compatibility method (deprecated)
    public function route(string $path, string $controller): void
    {
        $this->endpoint($path, $controller, ['deprecated' => true]);
    }
}
```

**Check Version in Listener:**

```php
public function onApiEndpoints(ApiEndpointsRegistering $event): void
{
    if (version_compare($event->version(), '2.0.0', '>=')) {
        // Use v2 API
        $event->endpoint('/posts', PostController::class, [
            'middleware' => ['auth:sanctum'],
        ]);
    } else {
        // Use v1 API (deprecated)
        $event->route('/posts', PostController::class);
    }
}
```

## Event Priority

Control listener execution order:

```php
<?php

namespace Mod\Core\Events;

use Core\Events\LifecycleEvent;

class ThemesRegistering extends LifecycleEvent
{
    protected array $themes = [];

    public function theme(string $name, string $class, int $priority = 0): void
    {
        $this->themes[] = compact('name', 'class', 'priority');
    }

    public function getThemes(): array
    {
        // Sort by priority (higher first)
        usort($this->themes, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return $this->themes;
    }
}
```

**Usage:**

```php
public function onThemes(ThemesRegistering $event): void
{
    $event->theme('default', DefaultTheme::class, priority: 0);
    $event->theme('premium', PremiumTheme::class, priority: 100);
}
```

## Event Validation

Validate registrations:

```php
<?php

namespace Mod\Forms\Events;

use Core\Events\LifecycleEvent;
use InvalidArgumentException;

class FormFieldsRegistering extends LifecycleEvent
{
    protected array $fields = [];

    public function field(string $type, string $class): void
    {
        // Validate field class
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Field class {$class} does not exist");
        }

        if (!is_subclass_of($class, FormField::class)) {
            throw new InvalidArgumentException("Field class must extend FormField");
        }

        $this->fields[$type] = $class;
    }

    public function getFields(): array
    {
        return $this->fields;
    }
}
```

## Event Documentation

Document your events with docblocks:

```php
<?php

namespace Mod\Media\Events;

use Core\Events\LifecycleEvent;

/**
 * Fired when media processors are being registered.
 *
 * Allows modules to register custom image/video processors.
 *
 * @example
 * ```php
 * public function onMediaProcessors(MediaProcessorsRegistering $event): void
 * {
 *     $event->processor('watermark', WatermarkProcessor::class);
 *     $event->processor('thumbnail', ThumbnailProcessor::class);
 * }
 * ```
 */
class MediaProcessorsRegistering extends LifecycleEvent
{
    protected array $processors = [];

    /**
     * Register a media processor.
     *
     * @param string $name Processor name (e.g., 'watermark')
     * @param string $class Processor class (must implement ProcessorInterface)
     */
    public function processor(string $name, string $class): void
    {
        $this->processors[$name] = $class;
    }

    /**
     * Get all registered processors.
     *
     * @return array<string, string>
     */
    public function getProcessors(): array
    {
        return $this->processors;
    }
}
```

## Testing Custom Events

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Mod\Shop\Events\PaymentGatewaysRegistering;
use Mod\Stripe\StripeGateway;

class PaymentGatewaysEventTest extends TestCase
{
    public function test_fires_payment_gateways_event(): void
    {
        Event::fake([PaymentGatewaysRegistering::class]);

        // Trigger module boot
        $this->app->boot();

        Event::assertDispatched(PaymentGatewaysRegistering::class);
    }

    public function test_registers_payment_gateway(): void
    {
        $event = new PaymentGatewaysRegistering();

        $event->gateway('stripe', StripeGateway::class);

        $this->assertEquals(
            ['stripe' => StripeGateway::class],
            $event->getGateways()
        );
    }

    public function test_stripe_module_registers_gateway(): void
    {
        $event = new PaymentGatewaysRegistering();

        $boot = new \Mod\Stripe\Boot();
        $boot->onPaymentGateways($event);

        $this->assertArrayHasKey('stripe', $event->getGateways());
    }
}
```

## Best Practices

### 1. Use Descriptive Names

```php
// ✅ Good
class PaymentGatewaysRegistering extends LifecycleEvent

// ❌ Bad
class RegisterGateways extends LifecycleEvent
```

### 2. Provide Fluent API

```php
// ✅ Good - chainable
public function gateway(string $name, string $class): self
{
    $this->gateways[$name] = $class;
    return $this;
}

// Usage:
$event->gateway('stripe', StripeGateway::class)
    ->gateway('paypal', PayPalGateway::class);
```

### 3. Validate Early

```php
// ✅ Good - validate on registration
public function gateway(string $name, string $class): void
{
    if (!class_exists($class)) {
        throw new InvalidArgumentException("Gateway class not found: {$class}");
    }

    $this->gateways[$name] = $class;
}
```

### 4. Version Your Events

```php
// ✅ Good - versioned
use HasEventVersion;

public function version(): string
{
    return '1.0.0';
}
```

## Real-World Example

Complete example of a custom event system:

```php
// Event
class SearchProvidersRegistering extends LifecycleEvent
{
    use HasEventVersion;

    protected array $providers = [];

    public function provider(
        string $name,
        string $class,
        int $priority = 0,
        array $config = []
    ): void {
        $this->providers[$name] = compact('class', 'priority', 'config');
    }

    public function getProviders(): array
    {
        uasort($this->providers, fn($a, $b) => $b['priority'] <=> $a['priority']);
        return $this->providers;
    }

    public function version(): string
    {
        return '1.0.0';
    }
}

// Fire event
$event = new SearchProvidersRegistering();
event($event);

foreach ($event->getProviders() as $name => $config) {
    app('search')->register($name, new $config['class']($config['config']));
}

// Listen to event
class Boot
{
    public static array $listens = [
        SearchProvidersRegistering::class => 'onSearchProviders',
    ];

    public function onSearchProviders(SearchProvidersRegistering $event): void
    {
        $event->provider('posts', PostSearchProvider::class, priority: 100);
        $event->provider('users', UserSearchProvider::class, priority: 50);
    }
}
```

## Learn More

- [Lifecycle Events →](/packages/core/events)
- [Module System →](/packages/core/modules)
