---
layout: home

hero:
  name: Core PHP Framework
  text: Modular Monolith for Laravel
  tagline: Event-driven architecture with lazy module loading and built-in multi-tenancy
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/host-uk/core-php

features:
  - icon: ⚡️
    title: Event-Driven Modules
    details: Modules declare interest in lifecycle events and are only loaded when needed, reducing overhead for unused features.

  - icon: 🔒
    title: Multi-Tenant Isolation
    details: Automatic workspace scoping for Eloquent models with strict mode enforcement prevents data leakage.

  - icon: 🎯
    title: Actions Pattern
    details: Extract business logic into testable, reusable classes with automatic dependency injection.

  - icon: 📝
    title: Activity Logging
    details: Built-in audit trails for model changes with minimal setup using Spatie Activity Log.

  - icon: 🌱
    title: Seeder Auto-Discovery
    details: Automatic seeder ordering via priority and dependency attributes eliminates manual registration.

  - icon: 🎨
    title: HLCRF Layouts
    details: Data-driven composable layouts with infinite nesting for flexible UI structures.

  - icon: 🔐
    title: Security First
    details: Bouncer action gates, request whitelisting, and comprehensive input sanitization.

  - icon: 🚀
    title: Production Ready
    details: Battle-tested in production with comprehensive test coverage and security audits.
---

## Quick Start

```bash
# Install via Composer
composer require host-uk/core

# Create a module
php artisan make:mod Commerce

# Register lifecycle events
class Boot
{
    public static array $listens = [
        WebRoutesRegistering::class => 'onWebRoutes',
    ];

    public function onWebRoutes(WebRoutesRegistering $event): void
    {
        $event->routes(fn () => require __DIR__.'/Routes/web.php');
    }
}
```

## Why Core PHP?

Traditional Laravel applications grow into monoliths with tight coupling and unclear boundaries. Microservices add complexity you may not need. **Core PHP provides a middle ground**: a structured monolith with clear module boundaries, lazy loading, and the ability to extract services later if needed.

### Key Benefits

- **Reduced Complexity** - No network overhead, distributed tracing, or service mesh
- **Clear Boundaries** - Modules have explicit dependencies via lifecycle events
- **Performance** - Lazy loading means unused modules aren't loaded
- **Flexibility** - Start monolithic, extract services when it makes sense
- **Type Safety** - Full IDE support with no RPC serialization

## Packages

<div class="package-grid">

### [Core](/packages/core)
Event-driven architecture, module system, actions pattern, and multi-tenancy.

### [Admin](/packages/admin)
Livewire-powered admin panel with global search and service management.

### [API](/packages/api)
REST API with OpenAPI docs, rate limiting, webhook signing, and secure keys.

### [MCP](/packages/mcp)
Model Context Protocol tools for AI integrations with analytics and security.

</div>

## Community

- **GitHub Discussions** - Ask questions and share ideas
- **Issue Tracker** - Report bugs and request features
- **Contributing** - See our [contributing guide](/contributing)

<style>
.package-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
  margin-top: 2rem;
}

.package-grid > div {
  padding: 1rem;
  border: 1px solid var(--vp-c-divider);
  border-radius: 8px;
}

.package-grid h3 {
  margin-top: 0;
}
</style>
