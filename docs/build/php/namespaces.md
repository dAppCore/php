# Namespaces & Entitlements

Core PHP Framework provides a sophisticated namespace and entitlements system for flexible multi-tenant SaaS applications. Namespaces provide universal tenant boundaries, while entitlements control feature access and usage limits.

## Overview

### The Problem

Traditional multi-tenant systems force a choice:

**Option A: User Ownership**
- Individual users own resources
- No team collaboration
- Billing per user

**Option B: Workspace Ownership**
- Teams own resources via workspaces
- Can't have personal resources
- Billing per workspace

Both approaches are too rigid for modern SaaS:
- **Agencies** need separate namespaces per client
- **Freelancers** want personal AND client resources
- **White-label operators** need brand isolation
- **Enterprise teams** need department-level isolation

### The Solution: Namespaces

Namespaces provide a **polymorphic ownership boundary** where resources belong to a namespace, and namespaces can be owned by either Users or Workspaces.

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│  User ────┬──→ Namespace (Personal)  ──→ Resources         │
│           │                                                 │
│           └──→ Workspace ──→ Namespace (Client A) ──→ Res   │
│                         └──→ Namespace (Client B) ──→ Res   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Benefits:**
- Users can have personal namespaces
- Workspaces can have multiple namespaces (one per client)
- Clean billing boundaries
- Complete resource isolation
- Flexible permission models

## Namespace Model

### Structure

```php
Namespace {
    id: int
    uuid: string              // Public identifier
    name: string              // Display name
    slug: string              // URL-safe identifier
    description: ?string
    icon: ?string
    color: ?string
    owner_type: string        // User::class or Workspace::class
    owner_id: int
    workspace_id: ?int        // Billing context (optional)
    settings: ?json
    is_default: bool          // User's default namespace
    is_active: bool
    sort_order: int
}
```

### Ownership Patterns

#### Personal Namespace (User-Owned)

Individual user owns namespace for personal resources:

```php
$namespace = Namespace_::create([
    'name' => 'Personal',
    'owner_type' => User::class,
    'owner_id' => $user->id,
    'workspace_id' => $user->defaultHostWorkspace()->id, // For billing
    'is_default' => true,
]);
```

**Use Cases:**
- Personal projects
- Individual freelancer work
- Testing/development environments

#### Agency Namespace (Workspace-Owned)

Workspace owns namespace for client/project isolation:

```php
$namespace = Namespace_::create([
    'name' => 'Client: Acme Corp',
    'slug' => 'acme-corp',
    'owner_type' => Workspace::class,
    'owner_id' => $workspace->id,
    'workspace_id' => $workspace->id, // Same workspace for billing
]);
```

**Use Cases:**
- Agency client projects
- White-label deployments
- Department/team isolation

#### White-Label Namespace

SaaS operator creates namespaces for customers:

```php
$namespace = Namespace_::create([
    'name' => 'Customer Instance',
    'owner_type' => User::class,        // Customer user owns it
    'owner_id' => $customerUser->id,
    'workspace_id' => $operatorWorkspace->id, // Operator billed
]);
```

**Use Cases:**
- White-label SaaS
- Reseller programs
- Managed services

## Using Namespaces

### Model Setup

Add namespace scoping to models:

```php
<?php

namespace Mod\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Core\Mod\Tenant\Concerns\BelongsToNamespace;

class Page extends Model
{
    use BelongsToNamespace;

    protected $fillable = ['title', 'content', 'slug'];
}
```

**Migration:**

```php
Schema::create('pages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('namespace_id')
        ->constrained('namespaces')
        ->cascadeOnDelete();
    $table->string('title');
    $table->text('content');
    $table->string('slug');
    $table->timestamps();

    $table->index(['namespace_id', 'created_at']);
});
```

### Automatic Scoping

The `BelongsToNamespace` trait automatically handles scoping:

```php
// Queries automatically scoped to current namespace
$pages = Page::ownedByCurrentNamespace()->get();

// Create automatically assigns namespace_id
$page = Page::create([
    'title' => 'Example Page',
    'content' => 'Content...',
    // namespace_id added automatically
]);

// Can't access pages from other namespaces
$page = Page::find(999); // null if belongs to different namespace
```

### Namespace Context

#### Middleware Resolution

```php
// routes/web.php
Route::middleware(['auth', 'namespace'])
    ->group(function () {
        Route::get('/pages', [PageController::class, 'index']);
    });
```

The `ResolveNamespace` middleware sets current namespace from:
1. Query parameter: `?namespace=uuid`
2. Request header: `X-Namespace: uuid`
3. Session: `current_namespace_uuid`
4. User's default namespace

#### Manual Context

```php
use Core\Mod\Tenant\Services\NamespaceService;

$namespaceService = app(NamespaceService::class);

// Get current namespace
$current = $namespaceService->current();

// Set current namespace
$namespaceService->setCurrent($namespace);

// Get all accessible namespaces
$namespaces = $namespaceService->accessibleByCurrentUser();

// Group by ownership
$grouped = $namespaceService->groupedForCurrentUser();
// [
//     'personal' => Collection,      // User-owned
//     'workspaces' => [               // Workspace-owned
//         ['workspace' => Workspace, 'namespaces' => Collection],
//         ...
//     ]
// ]
```

### Namespace Switcher UI

Provide namespace switching in your UI:

```blade
<div class="namespace-switcher">
    <x-dropdown>
        <x-slot:trigger>
            {{ $currentNamespace->name }}
        </x-slot>

        @foreach($personalNamespaces as $ns)
            <x-dropdown-item href="?namespace={{ $ns->uuid }}">
                {{ $ns->name }}
            </x-dropdown-item>
        @endforeach

        @foreach($workspaceNamespaces as $group)
            <x-dropdown-header>{{ $group['workspace']->name }}</x-dropdown-header>
            @foreach($group['namespaces'] as $ns)
                <x-dropdown-item href="?namespace={{ $ns->uuid }}">
                    {{ $ns->name }}
                </x-dropdown-item>
            @endforeach
        @endforeach
    </x-dropdown>
</div>
```

### API Integration

Include namespace in API requests:

```bash
# Header-based
curl -H "X-Namespace: uuid-here" \
     -H "Authorization: Bearer sk_live_..." \
     https://api.example.com/v1/pages

# Query parameter
curl "https://api.example.com/v1/pages?namespace=uuid-here" \
     -H "Authorization: Bearer sk_live_..."
```

## Entitlements System

Entitlements control **what users can do** within their namespaces. The system answers: *"Can this namespace perform this action?"*

### Core Concepts

#### Packages

Bundles of features with defined limits:

```php
Package {
    id: int
    code: string             // 'social-creator', 'bio-pro'
    name: string
    is_base_package: bool    // Only one base package per namespace
    is_stackable: bool       // Can have multiple addon packages
    is_active: bool
    is_public: bool          // Shown in pricing page
}
```

**Types:**
- **Base Package**: Core subscription (e.g., "Pro Plan")
- **Add-on Package**: Stackable extras (e.g., "Extra Storage")

#### Features

Capabilities or limits that can be granted:

```php
Feature {
    id: int
    code: string             // 'social.accounts', 'ai.credits'
    name: string
    type: enum               // boolean, limit, unlimited
    reset_type: enum         // none, monthly, rolling
    rolling_window_days: ?int
    parent_feature_id: ?int  // For hierarchical limits
    category: string         // 'social', 'ai', 'storage'
}
```

**Feature Types:**

| Type | Behavior | Example |
|------|----------|---------|
| **Boolean** | On/off access gate | `tier.apollo`, `host.social` |
| **Limit** | Numeric cap on usage | `social.accounts: 5`, `ai.credits: 100` |
| **Unlimited** | No cap | `social.posts: unlimited` |

**Reset Types:**

| Reset Type | Behavior | Example |
|------------|----------|---------|
| **None** | Usage accumulates forever | Account limits |
| **Monthly** | Resets at billing cycle start | API requests per month |
| **Rolling** | Rolling window (e.g., last 30 days) | Posts per day |

#### Hierarchical Features (Pools)

Child features share a parent's limit pool:

```
host.storage.total (1000 MB) ← Parent pool
├── host.cdn             ← Draws from parent
├── bio.cdn              ← Draws from parent
└── social.cdn           ← Draws from parent
```

**Configuration:**

```php
Feature::create([
    'code' => 'host.storage.total',
    'name' => 'Total Storage',
    'type' => 'limit',
    'reset_type' => 'none',
]);

Feature::create([
    'code' => 'bio.cdn',
    'name' => 'Bio Link Storage',
    'type' => 'limit',
    'parent_feature_id' => $parentFeature->id, // Shares pool
]);
```

### Entitlement Checks

Use the entitlement service to check permissions:

```php
use Core\Mod\Tenant\Services\EntitlementService;

$entitlements = app(EntitlementService::class);

// Check if namespace can use feature
$result = $entitlements->can($namespace, 'social.accounts', quantity: 3);

if ($result->isDenied()) {
    return back()->with('error', $result->getMessage());
}

// Proceed with action...

// Record usage
$entitlements->recordUsage($namespace, 'social.accounts', quantity: 1);
```

### Entitlement Result

The `EntitlementResult` object provides complete context:

```php
$result = $entitlements->can($namespace, 'ai.credits', quantity: 10);

// Status checks
$result->isAllowed();        // true/false
$result->isDenied();         // true/false
$result->isUnlimited();      // true if unlimited

// Limits
$result->limit;              // 100
$result->used;               // 75
$result->remaining;          // 25

// Percentage
$result->getUsagePercentage(); // 75.0
$result->isNearLimit();      // true if > 80%

// Denial reason
$result->getMessage();       // "Exceeded limit for ai.credits"
```

### Usage Tracking

Record consumption after successful actions:

```php
$entitlements->recordUsage(
    namespace: $namespace,
    featureCode: 'ai.credits',
    quantity: 10,
    user: $user,           // Optional: who triggered it
    metadata: [            // Optional: context
        'model' => 'claude-3',
        'tokens' => 1500,
    ]
);
```

**Database Schema:**

```php
usage_records {
    id: int
    namespace_id: int
    feature_id: int
    workspace_id: ?int       // For workspace-level aggregation
    user_id: ?int
    quantity: int
    metadata: ?json
    created_at: timestamp
}
```

### Boosts

Temporary or permanent additions to limits:

```php
Boost {
    id: int
    namespace_id: int
    feature_id: int
    boost_type: enum         // add_limit, enable, unlimited
    duration_type: enum      // cycle_bound, duration, permanent
    limit_value: ?int        // Amount to add
    consumed_quantity: int   // How much used
    expires_at: ?timestamp
    status: enum             // active, exhausted, expired
}
```

**Use Cases:**
- One-time credit top-ups
- Promotional extras
- Beta access grants
- Temporary unlimited access

**Example:**

```php
// Give 1000 bonus AI credits
Boost::create([
    'namespace_id' => $namespace->id,
    'feature_id' => $aiCreditsFeature->id,
    'boost_type' => 'add_limit',
    'duration_type' => 'cycle_bound', // Expires at billing cycle end
    'limit_value' => 1000,
]);
```

### Package Assignment

Namespaces subscribe to packages:

```php
NamespacePackage {
    id: int
    namespace_id: int
    package_id: int
    status: enum             // active, suspended, cancelled, expired
    starts_at: timestamp
    expires_at: ?timestamp
    billing_cycle_anchor: timestamp
}
```

**Provision Package:**

```php
$entitlements->provisionPackage(
    namespace: $namespace,
    package: $package,
    startsAt: now(),
    expiresAt: now()->addMonth(),
);
```

**Package Features:**

Features are attached to packages with specific limits:

```php
// Package definition
$package = Package::find($packageId);

// Attach features with limits
$package->features()->attach($feature->id, [
    'limit_value' => 5, // This package grants 5 accounts
]);

// Multiple features
$package->features()->sync([
    $socialAccountsFeature->id => ['limit_value' => 5],
    $aiCreditsFeature->id => ['limit_value' => 100],
    $storageFeature->id => ['limit_value' => 1000], // MB
]);
```

## Usage Dashboard

Display usage stats to users:

```php
$summary = $entitlements->getUsageSummary($namespace);

// Returns array grouped by category:
[
    'social' => [
        [
            'feature' => Feature,
            'limit' => 5,
            'used' => 3,
            'remaining' => 2,
            'percentage' => 60.0,
            'is_unlimited' => false,
        ],
        ...
    ],
    'ai' => [...],
]
```

**UI Example:**

```blade
@foreach($summary as $category => $features)
    <div class="category">
        <h3>{{ ucfirst($category) }}</h3>

        @foreach($features as $item)
            <div class="feature-usage">
                <div class="feature-name">
                    {{ $item['feature']->name }}
                </div>

                @if($item['is_unlimited'])
                    <div class="badge">Unlimited</div>
@else
                    <div class="progress-bar">
                        <div class="progress-fill"
                             style="width: {{ $item['percentage'] }}%"
                             class="{{ $item['percentage'] > 80 ? 'text-red-600' : 'text-green-600' }}">
                        </div>
                    </div>

                    <div class="usage-text">
                        {{ $item['used'] }} / {{ $item['limit'] }}
                        ({{ number_format($item['percentage'], 1) }}%)
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endforeach
```

## Billing Integration

### Billing Context

Namespaces use `workspace_id` for billing aggregation:

```php
// Get billing workspace
$billingWorkspace = $namespace->getBillingContext();

// User-owned namespace → User's default workspace
// Workspace-owned namespace → Owner workspace
// Explicit workspace_id → That workspace
```

### Commerce Integration

Link subscriptions to namespace packages:

```php
// When subscription created
event(new SubscriptionCreated($subscription));

// Listener provisions package
$entitlements->provisionPackage(
    namespace: $subscription->namespace,
    package: $subscription->package,
    startsAt: $subscription->starts_at,
    expiresAt: $subscription->expires_at,
);

// When subscription renewed
$namespacePackage->update([
    'expires_at' => $subscription->next_billing_date,
    'billing_cycle_anchor' => now(),
]);

// Expire cycle-bound boosts
Boost::where('namespace_id', $namespace->id)
    ->where('duration_type', 'cycle_bound')
    ->update(['status' => 'expired']);
```

### External Billing Systems

API endpoints for external billing (Blesta, Stripe, etc.):

```bash
# Provision package
POST /api/v1/entitlements
{
    "namespace_uuid": "uuid",
    "package_code": "social-creator",
    "starts_at": "2026-01-01T00:00:00Z",
    "expires_at": "2026-02-01T00:00:00Z"
}

# Suspend package
POST /api/v1/entitlements/{id}/suspend

# Cancel package
POST /api/v1/entitlements/{id}/cancel

# Renew package
POST /api/v1/entitlements/{id}/renew
{
    "expires_at": "2026-03-01T00:00:00Z"
}

# Check entitlements
GET /api/v1/entitlements/check
    ?namespace=uuid
    &feature=social.accounts
    &quantity=1
```

## Audit Logging

All entitlement changes are logged:

```php
EntitlementLog {
    id: int
    namespace_id: int
    workspace_id: ?int
    action: enum             // package_provisioned, boost_expired, etc.
    source: enum             // blesta, commerce, admin, system, api
    user_id: ?int
    data: json               // Context about the change
    created_at: timestamp
}
```

**Actions:**
- `package_provisioned`, `package_suspended`, `package_cancelled`
- `boost_provisioned`, `boost_exhausted`, `boost_expired`
- `usage_recorded`, `usage_denied`

**Retrieve logs:**

```php
$logs = EntitlementLog::where('namespace_id', $namespace->id)
    ->latest()
    ->paginate(20);
```

## Feature Seeder

Define features in seeders:

```php
<?php

namespace Mod\Tenant\Database\Seeders;

use Illuminate\Database\Seeder;
use Core\Mod\Tenant\Models\Feature;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        // Tier features (boolean gates)
        Feature::create([
            'code' => 'tier.apollo',
            'name' => 'Apollo Tier',
            'type' => 'boolean',
            'category' => 'tier',
        ]);

        // Social features
        Feature::create([
            'code' => 'social.accounts',
            'name' => 'Social Accounts',
            'type' => 'limit',
            'reset_type' => 'none',
            'category' => 'social',
        ]);

        Feature::create([
            'code' => 'social.posts.scheduled',
            'name' => 'Scheduled Posts',
            'type' => 'limit',
            'reset_type' => 'monthly',
            'category' => 'social',
        ]);

        // AI features
        Feature::create([
            'code' => 'ai.credits',
            'name' => 'AI Credits',
            'type' => 'limit',
            'reset_type' => 'monthly',
            'category' => 'ai',
        ]);

        // Storage pool
        $storagePool = Feature::create([
            'code' => 'host.storage.total',
            'name' => 'Total Storage',
            'type' => 'limit',
            'reset_type' => 'none',
            'category' => 'storage',
        ]);

        // Child features share pool
        Feature::create([
            'code' => 'host.cdn',
            'name' => 'CDN Storage',
            'type' => 'limit',
            'parent_feature_id' => $storagePool->id,
            'category' => 'storage',
        ]);
    }
}
```

## Testing

### Test Namespace Isolation

```php
public function test_cannot_access_other_namespace_resources(): void
{
    $namespace1 = Namespace_::factory()->create();
    $namespace2 = Namespace_::factory()->create();

    $page = Page::factory()->for($namespace1, 'namespace')->create();

    // Set context to namespace2
    request()->attributes->set('current_namespace', $namespace2);

    // Should not find page from namespace1
    $this->assertNull(Page::ownedByCurrentNamespace()->find($page->id));
}
```

### Test Entitlements

```php
public function test_enforces_feature_limits(): void
{
    $namespace = Namespace_::factory()->create();

    $package = Package::factory()->create();
    $feature = Feature::factory()->create([
        'code' => 'social.accounts',
        'type' => 'limit',
    ]);

    $package->features()->attach($feature->id, ['limit_value' => 5]);

    $entitlements = app(EntitlementService::class);
    $entitlements->provisionPackage($namespace, $package);

    // Can create up to limit
    for ($i = 0; $i < 5; $i++) {
        $result = $entitlements->can($namespace, 'social.accounts');
        $this->assertTrue($result->isAllowed());
        $entitlements->recordUsage($namespace, 'social.accounts');
    }

    // 6th attempt denied
    $result = $entitlements->can($namespace, 'social.accounts');
    $this->assertTrue($result->isDenied());
}
```

## Best Practices

### 1. Always Use Namespace Scoping

```php
// ✅ Good - scoped to namespace
class Page extends Model
{
    use BelongsToNamespace;
}

// ❌ Bad - no isolation
class Page extends Model { }
```

### 2. Check Entitlements Before Actions

```php
// ✅ Good - check before creating
$result = $entitlements->can($namespace, 'social.accounts');
if ($result->isDenied()) {
    return back()->with('error', $result->getMessage());
}

SocialAccount::create($data);
$entitlements->recordUsage($namespace, 'social.accounts');

// ❌ Bad - no entitlement check
SocialAccount::create($data);
```

### 3. Use Descriptive Feature Codes

```php
// ✅ Good - clear hierarchy
'social.accounts'
'social.posts.scheduled'
'ai.credits.claude'

// ❌ Bad - unclear
'accounts'
'posts'
'credits'
```

### 4. Provide Usage Visibility

Always show users their current usage and limits in the UI.

### 5. Log Entitlement Changes

All provisioning, suspension, and cancellation should be logged for audit purposes.

## Migration from Workspace-Only

If migrating from workspace-only system:

```php
// Create namespace for each workspace
foreach (Workspace::all() as $workspace) {
    $namespace = Namespace_::create([
        'name' => $workspace->name,
        'owner_type' => Workspace::class,
        'owner_id' => $workspace->id,
        'workspace_id' => $workspace->id,
        'is_default' => true,
    ]);

    // Migrate existing resources
    Resource::where('workspace_id', $workspace->id)
        ->update(['namespace_id' => $namespace->id]);

    // Migrate packages
    WorkspacePackage::where('workspace_id', $workspace->id)
        ->each(function ($wp) use ($namespace) {
            NamespacePackage::create([
                'namespace_id' => $namespace->id,
                'package_id' => $wp->package_id,
                'status' => $wp->status,
                'starts_at' => $wp->starts_at,
                'expires_at' => $wp->expires_at,
            ]);
        });
}
```

## Learn More

- [Multi-Tenancy Architecture →](/architecture/multi-tenancy)
- [Entitlements RFC](https://github.com/host-uk/core-php/blob/main/docs/rfc/RFC-004-ENTITLEMENTS.md)
- [API Package →](/packages/api)
- [Security Overview →](/security/overview)
