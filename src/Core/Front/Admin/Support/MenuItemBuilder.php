<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Admin\Support;

use Core\Front\Admin\Contracts\AdminMenuProvider;

/**
 * Fluent builder for constructing admin menu items.
 *
 * Provides a chainable API for building menu item configurations
 * without manually constructing nested arrays.
 *
 * ## Basic Usage
 *
 * ```php
 * use Core\Front\Admin\Support\MenuItemBuilder;
 *
 * $item = MenuItemBuilder::make('Products')
 *     ->icon('cube')
 *     ->href('/admin/products')
 *     ->inGroup('services')
 *     ->withPriority(AdminMenuProvider::PRIORITY_NORMAL)
 *     ->build();
 * ```
 *
 * ## With Children
 *
 * ```php
 * $item = MenuItemBuilder::make('Commerce')
 *     ->icon('shopping-cart')
 *     ->href('/admin/commerce')
 *     ->inGroup('services')
 *     ->entitlement('core.srv.commerce')
 *     ->children([
 *         MenuItemBuilder::child('Products', '/products')->icon('cube'),
 *         MenuItemBuilder::child('Orders', '/orders')->icon('receipt'),
 *     ])
 *     ->build();
 * ```
 *
 * ## With Permissions
 *
 * ```php
 * $item = MenuItemBuilder::make('Settings')
 *     ->icon('gear')
 *     ->href('/admin/settings')
 *     ->requireAdmin()
 *     ->permissions(['settings.view', 'settings.edit'])
 *     ->build();
 * ```
 *
 *
 * @see AdminMenuProvider For menu provider interface
 * @see MenuItemGroup For grouping utilities
 */
class MenuItemBuilder
{
    /**
     * The menu item label.
     */
    protected string $label;

    /**
     * The menu item icon (FontAwesome name).
     */
    protected ?string $icon = null;

    /**
     * The menu item URL/href.
     */
    protected ?string $href = null;

    /**
     * Route name for href generation.
     */
    protected ?string $route = null;

    /**
     * Route parameters for href generation.
     *
     * @var array<string, mixed>
     */
    protected array $routeParams = [];

    /**
     * Menu group (dashboard, workspaces, services, settings, admin).
     */
    protected string $group = 'services';

    /**
     * Priority within the group.
     */
    protected int $priority = AdminMenuProvider::PRIORITY_NORMAL;

    /**
     * Entitlement code for access control.
     */
    protected ?string $entitlement = null;

    /**
     * Required permissions array.
     *
     * @var array<string>
     */
    protected array $permissions = [];

    /**
     * Whether admin access is required.
     */
    protected bool $admin = false;

    /**
     * Color theme for the item.
     */
    protected ?string $color = null;

    /**
     * Badge text or configuration.
     *
     * @var string|array<string, mixed>|null
     */
    protected string|array|null $badge = null;

    /**
     * Child menu items.
     *
     * @var array<int, MenuItemBuilder|array>
     */
    protected array $children = [];

    /**
     * Closure to determine active state.
     */
    protected ?\Closure $activeCallback = null;

    /**
     * Whether the item is currently active.
     */
    protected ?bool $active = null;

    /**
     * Service key for service-specific lookups.
     */
    protected ?string $service = null;

    /**
     * Additional custom attributes.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * Create a new menu item builder.
     *
     * @param  string  $label  The menu item display text
     */
    public function __construct(string $label)
    {
        $this->label = $label;
    }

    /**
     * Create a new menu item builder (static factory).
     *
     * @param  string  $label  The menu item display text
     */
    public static function make(string $label): static
    {
        return new static($label);
    }

    /**
     * Create a child menu item builder.
     *
     * Convenience factory for creating sub-menu items with a relative href.
     *
     * @param  string  $label  The child item label
     * @param  string  $href  The child item URL
     */
    public static function child(string $label, string $href): static
    {
        return (new static($label))->href($href);
    }

    /**
     * Set the icon name (FontAwesome).
     *
     * @param  string  $icon  Icon name (e.g., 'home', 'gear', 'fa-solid fa-user')
     * @return $this
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set the URL/href for the menu item.
     *
     * @param  string  $href  The URL path
     * @return $this
     */
    public function href(string $href): static
    {
        $this->href = $href;

        return $this;
    }

    /**
     * Set the route name for href generation.
     *
     * The href will be generated using Laravel's route() helper at build time.
     *
     * @param  string  $route  The route name
     * @param  array<string, mixed>  $params  Optional route parameters
     * @return $this
     */
    public function route(string $route, array $params = []): static
    {
        $this->route = $route;
        $this->routeParams = $params;

        return $this;
    }

    /**
     * Set the menu group.
     *
     * @param  string  $group  Group key (dashboard, workspaces, services, settings, admin)
     * @return $this
     */
    public function inGroup(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Place in the dashboard group.
     *
     * @return $this
     */
    public function inDashboard(): static
    {
        return $this->inGroup('dashboard');
    }

    /**
     * Place in the workspaces group.
     *
     * @return $this
     */
    public function inWorkspaces(): static
    {
        return $this->inGroup('workspaces');
    }

    /**
     * Place in the services group (default).
     *
     * @return $this
     */
    public function inServices(): static
    {
        return $this->inGroup('services');
    }

    /**
     * Place in the settings group.
     *
     * @return $this
     */
    public function inSettings(): static
    {
        return $this->inGroup('settings');
    }

    /**
     * Place in the admin group.
     *
     * @return $this
     */
    public function inAdmin(): static
    {
        return $this->inGroup('admin');
    }

    /**
     * Set the priority within the group.
     *
     * @param  int  $priority  Use AdminMenuProvider::PRIORITY_* constants
     * @return $this
     */
    public function withPriority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Alias for withPriority().
     *
     * @param  int  $priority  Priority value
     * @return $this
     */
    public function priority(int $priority): static
    {
        return $this->withPriority($priority);
    }

    /**
     * Set to highest priority (first in group).
     *
     * @return $this
     */
    public function first(): static
    {
        return $this->withPriority(AdminMenuProvider::PRIORITY_FIRST);
    }

    /**
     * Set to high priority.
     *
     * @return $this
     */
    public function high(): static
    {
        return $this->withPriority(AdminMenuProvider::PRIORITY_HIGH);
    }

    /**
     * Set to low priority.
     *
     * @return $this
     */
    public function low(): static
    {
        return $this->withPriority(AdminMenuProvider::PRIORITY_LOW);
    }

    /**
     * Set to lowest priority (last in group).
     *
     * @return $this
     */
    public function last(): static
    {
        return $this->withPriority(AdminMenuProvider::PRIORITY_LAST);
    }

    /**
     * Set the entitlement code for workspace-level access control.
     *
     * @param  string  $entitlement  The feature code (e.g., 'core.srv.commerce')
     * @return $this
     */
    public function entitlement(string $entitlement): static
    {
        $this->entitlement = $entitlement;

        return $this;
    }

    /**
     * Alias for entitlement().
     *
     * @param  string  $entitlement  The feature code
     * @return $this
     */
    public function requiresEntitlement(string $entitlement): static
    {
        return $this->entitlement($entitlement);
    }

    /**
     * Set required permissions.
     *
     * @param  array<string>  $permissions  Array of permission keys
     * @return $this
     */
    public function permissions(array $permissions): static
    {
        $this->permissions = $permissions;

        return $this;
    }

    /**
     * Add a single required permission.
     *
     * @param  string  $permission  The permission key
     * @return $this
     */
    public function permission(string $permission): static
    {
        $this->permissions[] = $permission;

        return $this;
    }

    /**
     * Alias for permissions().
     *
     * @param  array<string>  $permissions  Array of permission keys
     * @return $this
     */
    public function requiresPermissions(array $permissions): static
    {
        return $this->permissions($permissions);
    }

    /**
     * Require admin access (Hades user).
     *
     * @param  bool  $required  Whether admin is required
     * @return $this
     */
    public function requireAdmin(bool $required = true): static
    {
        $this->admin = $required;

        return $this;
    }

    /**
     * Alias for requireAdmin().
     *
     * @return $this
     */
    public function adminOnly(): static
    {
        return $this->requireAdmin(true);
    }

    /**
     * Set the color theme.
     *
     * @param  string  $color  Color name (e.g., 'blue', 'green', 'amber')
     * @return $this
     */
    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Set a text badge.
     *
     * @param  string  $text  Badge text
     * @param  string|null  $color  Optional badge color
     * @return $this
     */
    public function badge(string $text, ?string $color = null): static
    {
        if ($color !== null) {
            $this->badge = ['text' => $text, 'color' => $color];
        } else {
            $this->badge = $text;
        }

        return $this;
    }

    /**
     * Set a numeric badge with a count.
     *
     * @param  int  $count  The count to display
     * @param  string|null  $color  Optional badge color
     * @return $this
     */
    public function badgeCount(int $count, ?string $color = null): static
    {
        return $this->badge((string) $count, $color);
    }

    /**
     * Set a configurable badge.
     *
     * @param  array<string, mixed>  $config  Badge configuration
     * @return $this
     */
    public function badgeConfig(array $config): static
    {
        $this->badge = $config;

        return $this;
    }

    /**
     * Set child menu items.
     *
     * @param  array<int, MenuItemBuilder|array>  $children  Child items or builders
     * @return $this
     */
    public function children(array $children): static
    {
        $this->children = $children;

        return $this;
    }

    /**
     * Add a child menu item.
     *
     * @param  MenuItemBuilder|array  $child  Child item or builder
     * @return $this
     */
    public function addChild(MenuItemBuilder|array $child): static
    {
        $this->children[] = $child;

        return $this;
    }

    /**
     * Add a separator to children.
     *
     * @return $this
     */
    public function separator(): static
    {
        $this->children[] = MenuItemGroup::separator();

        return $this;
    }

    /**
     * Add a section header to children.
     *
     * @param  string  $label  Section label
     * @param  string|null  $icon  Optional icon
     * @return $this
     */
    public function section(string $label, ?string $icon = null): static
    {
        $this->children[] = MenuItemGroup::header($label, $icon);

        return $this;
    }

    /**
     * Add a divider to children.
     *
     * @param  string|null  $label  Optional divider label
     * @return $this
     */
    public function divider(?string $label = null): static
    {
        $this->children[] = MenuItemGroup::divider($label);

        return $this;
    }

    /**
     * Set whether the item is active.
     *
     * @param  bool  $active  Active state
     * @return $this
     */
    public function active(bool $active = true): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Set a callback to determine active state.
     *
     * The callback is evaluated at build time in the item closure.
     *
     * @param  \Closure  $callback  Callback returning bool
     * @return $this
     */
    public function activeWhen(\Closure $callback): static
    {
        $this->activeCallback = $callback;

        return $this;
    }

    /**
     * Set active when the current route matches a pattern.
     *
     * @param  string  $pattern  Route pattern (e.g., 'hub.commerce.*')
     * @return $this
     */
    public function activeOnRoute(string $pattern): static
    {
        return $this->activeWhen(fn () => request()->routeIs($pattern));
    }

    /**
     * Set the service key for service-specific lookups.
     *
     * @param  string  $key  Service key (e.g., 'commerce', 'bio')
     * @return $this
     */
    public function service(string $key): static
    {
        $this->service = $key;

        return $this;
    }

    /**
     * Set a custom attribute.
     *
     * @param  string  $key  Attribute key
     * @param  mixed  $value  Attribute value
     * @return $this
     */
    public function with(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Set multiple custom attributes.
     *
     * @param  array<string, mixed>  $attributes  Attributes array
     * @return $this
     */
    public function withAttributes(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Build the menu item registration array.
     *
     * Returns the structure expected by AdminMenuProvider::adminMenuItems().
     *
     * @return array{
     *     group: string,
     *     priority: int,
     *     entitlement?: string|null,
     *     permissions?: array<string>,
     *     admin?: bool,
     *     service?: string,
     *     item: \Closure
     * }
     */
    public function build(): array
    {
        $registration = [
            'group' => $this->group,
            'priority' => $this->priority,
            'item' => $this->buildItemClosure(),
        ];

        if ($this->entitlement !== null) {
            $registration['entitlement'] = $this->entitlement;
        }

        if (! empty($this->permissions)) {
            $registration['permissions'] = $this->permissions;
        }

        if ($this->admin) {
            $registration['admin'] = true;
        }

        if ($this->service !== null) {
            $registration['service'] = $this->service;
        }

        return $registration;
    }

    /**
     * Build the lazy-evaluated item closure.
     */
    protected function buildItemClosure(): \Closure
    {
        return function () {
            $item = [
                'label' => $this->label,
            ];

            // Resolve href
            if ($this->route !== null) {
                $item['href'] = route($this->route, $this->routeParams);
            } elseif ($this->href !== null) {
                $item['href'] = $this->href;
            } else {
                $item['href'] = '#';
            }

            // Optional icon
            if ($this->icon !== null) {
                $item['icon'] = $this->icon;
            }

            // Resolve active state
            if ($this->activeCallback !== null) {
                $item['active'] = ($this->activeCallback)();
            } elseif ($this->active !== null) {
                $item['active'] = $this->active;
            } else {
                $item['active'] = false;
            }

            // Optional color
            if ($this->color !== null) {
                $item['color'] = $this->color;
            }

            // Optional badge
            if ($this->badge !== null) {
                $item['badge'] = $this->badge;
            }

            // Build children
            if (! empty($this->children)) {
                $item['children'] = $this->buildChildren();
            }

            // Custom attributes
            foreach ($this->attributes as $key => $value) {
                if (! isset($item[$key])) {
                    $item[$key] = $value;
                }
            }

            return $item;
        };
    }

    /**
     * Build the children array.
     *
     * @return array<int, array>
     */
    protected function buildChildren(): array
    {
        $built = [];

        foreach ($this->children as $child) {
            if ($child instanceof MenuItemBuilder) {
                // Build the child item directly (not the registration)
                $built[] = $child->buildChildItem();
            } else {
                // Already an array (separator, header, etc.)
                $built[] = $child;
            }
        }

        return $built;
    }

    /**
     * Build a child item array (without registration wrapper).
     */
    public function buildChildItem(): array
    {
        $item = [
            'label' => $this->label,
        ];

        if ($this->route !== null) {
            $item['href'] = route($this->route, $this->routeParams);
        } elseif ($this->href !== null) {
            $item['href'] = $this->href;
        } else {
            $item['href'] = '#';
        }

        if ($this->icon !== null) {
            $item['icon'] = $this->icon;
        }

        if ($this->activeCallback !== null) {
            $item['active'] = ($this->activeCallback)();
        } elseif ($this->active !== null) {
            $item['active'] = $this->active;
        } else {
            $item['active'] = false;
        }

        if ($this->color !== null) {
            $item['color'] = $this->color;
        }

        if ($this->badge !== null) {
            $item['badge'] = $this->badge;
        }

        foreach ($this->attributes as $key => $value) {
            if (! isset($item[$key])) {
                $item[$key] = $value;
            }
        }

        return $item;
    }

    /**
     * Get the label.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the group.
     */
    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * Get the priority.
     */
    public function getPriority(): int
    {
        return $this->priority;
    }
}
