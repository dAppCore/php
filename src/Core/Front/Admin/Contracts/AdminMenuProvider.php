<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Admin\Contracts;

/**
 * Interface for modules that provide admin menu items.
 *
 * Modules implement this interface to contribute navigation items to the admin
 * panel sidebar. The `AdminMenuRegistry` collects items from all registered
 * providers and builds the final menu structure with proper ordering, grouping,
 * and permission filtering.
 *
 * ## Menu Item Structure
 *
 * Each item returned by `adminMenuItems()` specifies:
 *
 * - **group** - Where in the menu hierarchy (`dashboard`, `workspaces`, `services`, `settings`, `admin`)
 * - **priority** - Order within group (lower = earlier, see Priority Constants below)
 * - **entitlement** - Optional feature code for workspace-level access
 * - **permissions** - Optional array of required user permissions
 * - **admin** - Whether item requires Hades/admin user
 * - **item** - Closure returning the actual menu item data (lazy-evaluated)
 *
 * ## Menu Item Grouping
 *
 * Items with children can use grouping elements for better organization:
 *
 * - **separator** - Simple visual divider (`['separator' => true]`)
 * - **divider** - Divider with optional label (`['divider' => true, 'label' => 'More']`)
 * - **section** - Section header (`['section' => 'Products', 'icon' => 'cube']`)
 * - **collapsible** - Collapsible sub-group with state persistence
 *
 * Use `MenuItemGroup` helper for cleaner syntax:
 *
 * ```php
 * use Core\Front\Admin\Support\MenuItemGroup;
 *
 * 'children' => [
 *     MenuItemGroup::header('Products', 'cube'),
 *     ['label' => 'All Products', 'href' => '/products'],
 *     MenuItemGroup::separator(),
 *     MenuItemGroup::header('Orders', 'receipt'),
 *     ['label' => 'All Orders', 'href' => '/orders'],
 * ],
 * ```
 *
 * ## Priority Constants (Ordering Specification)
 *
 * Use these priority ranges to ensure consistent menu ordering across modules:
 *
 * | Range    | Constant              | Description                           |
 * |----------|-----------------------|---------------------------------------|
 * | 0-9      | PRIORITY_FIRST        | Reserved for system items             |
 * | 10-19    | PRIORITY_HIGH         | Primary navigation items              |
 * | 20-39    | PRIORITY_ABOVE_NORMAL | Important but not primary items       |
 * | 40-60    | PRIORITY_NORMAL       | Standard items (default: 50)          |
 * | 61-79    | PRIORITY_BELOW_NORMAL | Less important items                  |
 * | 80-89    | PRIORITY_LOW          | Rarely used items                     |
 * | 90-99    | PRIORITY_LAST         | Items that should appear at the end   |
 *
 * Within the same priority, items are ordered by registration order.
 *
 * ## Icon Validation
 *
 * Icons should be valid FontAwesome icon names. The `IconValidator` class
 * validates icons against known FontAwesome icons. Supported formats:
 *
 * - Shorthand: `home`, `user`, `gear`
 * - Full class: `fas fa-home`, `fa-solid fa-user`
 * - Brand icons: `fab fa-github`, `fa-brands fa-twitter`
 *
 * ## Lazy Evaluation
 *
 * The `item` closure is only called when the menu is rendered, after permission
 * checks pass. This avoids unnecessary work for filtered items and allows
 * route-dependent data (like `active` state) to be computed at render time.
 *
 * ## Registration
 *
 * Providers are typically registered via `AdminMenuRegistry::register()` during
 * the AdminPanelBooting event or in a service provider's boot method.
 *
 *
 * @see DynamicMenuProvider For uncached, real-time menu items
 * @see \Core\Front\Admin\Validation\IconValidator For icon validation
 */
interface AdminMenuProvider
{
    /**
     * Priority: Reserved for system items (0-9).
     */
    public const PRIORITY_FIRST = 0;

    /**
     * Priority: Primary navigation items (10-19).
     */
    public const PRIORITY_HIGH = 10;

    /**
     * Priority: Important but not primary items (20-39).
     */
    public const PRIORITY_ABOVE_NORMAL = 20;

    /**
     * Priority: Standard items, default (40-60).
     */
    public const PRIORITY_NORMAL = 50;

    /**
     * Priority: Less important items (61-79).
     */
    public const PRIORITY_BELOW_NORMAL = 70;

    /**
     * Priority: Rarely used items (80-89).
     */
    public const PRIORITY_LOW = 80;

    /**
     * Priority: Items that should appear at the end (90-99).
     */
    public const PRIORITY_LAST = 90;

    /**
     * Return admin menu items for this module.
     *
     * Each item should specify:
     * - group: string (dashboard|workspaces|services|settings|admin)
     * - priority: int (use PRIORITY_* constants for consistent ordering)
     * - entitlement: string|null (feature code for access check)
     * - permissions: array|null (required user permissions)
     * - admin: bool (requires Hades/admin user)
     * - item: Closure (lazy-evaluated menu item data)
     *
     * The item closure should return an array with:
     * - label: string (display text)
     * - icon: string (FontAwesome icon name, validated by IconValidator)
     * - href: string (link URL)
     * - active: bool (whether item is currently active)
     * - color: string|null (optional color theme)
     * - badge: string|array|null (optional badge text or config)
     * - children: array|null (optional sub-menu items)
     *
     * Example:
     * ```php
     * return [
     *     [
     *         'group' => 'services',
     *         'priority' => self::PRIORITY_NORMAL, // 50
     *         'entitlement' => 'core.srv.bio',
     *         'permissions' => ['bio.view', 'bio.manage'],
     *         'item' => fn() => [
     *             'label' => 'BioHost',
     *             'icon' => 'link',  // Validated against FontAwesome icons
     *             'href' => route('hub.bio.index'),
     *             'active' => request()->routeIs('hub.bio.*'),
     *             'children' => [...],
     *         ],
     *     ],
     * ];
     * ```
     *
     * @return array<int, array{
     *     group: string,
     *     priority: int,
     *     entitlement?: string|null,
     *     permissions?: array<string>|null,
     *     admin?: bool,
     *     item: \Closure
     * }>
     *
     * @see IconValidator For valid icon names
     */
    public function adminMenuItems(): array;

    /**
     * Get the permissions required to view any menu items from this provider.
     *
     * This provides a way to define global permission requirements for all
     * menu items from this provider. Individual items can override with their
     * own 'permissions' key in adminMenuItems().
     *
     * Return an empty array if no global permissions are required.
     *
     * @return array<string>
     */
    public function menuPermissions(): array;

    /**
     * Check if the user has permission to view menu items from this provider.
     *
     * Override this method to implement custom permission logic beyond
     * simple permission key checks.
     *
     * @param  object|null  $user  The authenticated user (User model instance)
     * @param  object|null  $workspace  The current workspace context (Workspace model instance)
     */
    public function canViewMenu(?object $user, ?object $workspace): bool;
}
