# Admin Menus

The Admin package provides an extensible menu system with automatic discovery, authorization, and icon support.

## Creating Menu Providers

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
                        ->icon('document-text')
                        ->badge(fn () => Post::draft()->count()),

                    MenuItemBuilder::make('Categories')
                        ->route('admin.blog.categories.index')
                        ->icon('folder'),

                    MenuItemBuilder::make('Tags')
                        ->route('admin.blog.tags.index')
                        ->icon('tag'),
                ])
                ->build(),
        ];
    }
}
```

## Registering Menus

```php
// In your Boot.php
public function onAdminPanel(AdminPanelBooting $event): void
{
    $event->menu(new BlogMenuProvider());
}
```

## Menu Item Properties

### Basic Item

```php
MenuItemBuilder::make('Dashboard')
    ->route('admin.dashboard')
    ->icon('home')
    ->build();
```

### With URL

```php
MenuItemBuilder::make('External Link')
    ->url('https://example.com')
    ->icon('external-link')
    ->external() // Opens in new tab
    ->build();
```

### With Children

```php
MenuItemBuilder::make('Content')
    ->icon('document')
    ->children([
        MenuItemBuilder::make('Posts')->route('admin.posts'),
        MenuItemBuilder::make('Pages')->route('admin.pages'),
    ])
    ->build();
```

### With Badge

```php
MenuItemBuilder::make('Comments')
    ->route('admin.comments')
    ->badge(fn () => Comment::pending()->count())
    ->badgeColor('red')
    ->build();
```

### With Authorization

```php
MenuItemBuilder::make('Settings')
    ->route('admin.settings')
    ->can('admin.settings.view')
    ->build();
```

### With Priority

```php
// Higher priority = appears first
MenuItemBuilder::make('Dashboard')
    ->priority(100)
    ->build();

MenuItemBuilder::make('Settings')
    ->priority(10)
    ->build();
```

## Advanced Examples

### Dynamic Menu Based on Permissions

```php
public function register(): array
{
    $menu = MenuItemBuilder::make('Blog')->icon('newspaper');

    if (Gate::allows('posts.view')) {
        $menu->child(MenuItemBuilder::make('Posts')->route('admin.blog.posts'));
    }

    if (Gate::allows('categories.view')) {
        $menu->child(MenuItemBuilder::make('Categories')->route('admin.blog.categories'));
    }

    return [$menu->build()];
}
```

### Menu with Active State

```php
MenuItemBuilder::make('Posts')
    ->route('admin.blog.posts')
    ->active(fn () => request()->routeIs('admin.blog.posts.*'))
    ->build();
```

### Menu with Count Badge

```php
MenuItemBuilder::make('Pending Reviews')
    ->route('admin.reviews.pending')
    ->badge(fn () => Review::pending()->count())
    ->badgeColor('yellow')
    ->badgeTooltip('Reviews awaiting moderation')
    ->build();
```

## Menu Groups

Organize related items:

```php
MenuItemBuilder::makeGroup('Content Management')
    ->priority(50)
    ->children([
        MenuItemBuilder::make('Posts')->route('admin.posts'),
        MenuItemBuilder::make('Pages')->route('admin.pages'),
        MenuItemBuilder::make('Media')->route('admin.media'),
    ])
    ->build();
```

## Icon Support

Menus support Heroicons:

```php
->icon('document-text')  // Document icon
->icon('users')          // Users icon
->icon('cog')            // Settings icon
->icon('chart-bar')      // Analytics icon
```

[Browse Heroicons →](https://heroicons.com)

## Best Practices

### 1. Use Meaningful Icons

```php
// ✅ Good - clear icon
MenuItemBuilder::make('Posts')->icon('document-text')

// ❌ Bad - generic icon
MenuItemBuilder::make('Posts')->icon('square')
```

### 2. Set Priorities

```php
// ✅ Good - logical ordering
MenuItemBuilder::make('Dashboard')->priority(100)
MenuItemBuilder::make('Posts')->priority(90)
MenuItemBuilder::make('Settings')->priority(10)
```

### 3. Use Authorization

```php
// ✅ Good - respects permissions
MenuItemBuilder::make('Settings')
    ->can('admin.settings.view')
```

### 4. Keep Hierarchy Shallow

```php
// ✅ Good - 2 levels max
Blog
  ├─ Posts
  └─ Categories

// ❌ Bad - too deep
Content
  └─ Blog
      └─ Posts
          └─ Published
```

## Learn More

- [Authorization →](/packages/admin/authorization)
- [Livewire Modals →](/packages/admin/modals)
