<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Admin;

use Core\Front\Admin\Contracts\AdminMenuProvider;
use Core\Front\Admin\Contracts\DynamicMenuProvider;
use Core\Front\Admin\Validation\IconValidator;
use Core\Tenant\Services\EntitlementService;
use Illuminate\Support\Facades\Cache;

/**
 * Registry for admin menu items.
 *
 * Modules register themselves during boot. The registry builds the complete
 * menu structure at render time, handling entitlement checks, permission
 * checks, caching, and sorting.
 */
class AdminMenuRegistry
{
    /**
     * Cache key prefix for menu items.
     */
    protected const CACHE_PREFIX = 'admin_menu';

    /**
     * Default cache TTL in seconds (5 minutes).
     */
    protected const DEFAULT_CACHE_TTL = 300;

    /**
     * Registered menu providers.
     *
     * @var array<AdminMenuProvider>
     */
    protected array $providers = [];

    /**
     * Registered dynamic menu providers.
     *
     * @var array<DynamicMenuProvider>
     */
    protected array $dynamicProviders = [];

    /**
     * Pre-defined menu groups with metadata.
     *
     * Groups with 'standalone' => true render items directly.
     * Other groups become dropdown parents with items as children.
     *
     * @var array<string, array>
     */
    protected array $groups = [
        'dashboard' => [
            'standalone' => true,
        ],
        'agents' => [
            'standalone' => true,
        ],
        'workspaces' => [
            'label' => 'Workspaces',
            'icon' => 'layer-group',
            'color' => 'blue',
        ],
        'services' => [
            'standalone' => true,
        ],
        'settings' => [
            'label' => 'Account',
            'icon' => 'gear',
            'color' => 'zinc',
        ],
        'admin' => [
            'label' => 'Admin',
            'icon' => 'shield',
            'color' => 'amber',
        ],
    ];

    /**
     * Whether caching is enabled.
     */
    protected bool $cachingEnabled = true;

    /**
     * Cache TTL in seconds.
     */
    protected int $cacheTtl;

    /**
     * EntitlementService instance (Core\Tenant\Services\EntitlementService when available).
     */
    protected ?object $entitlements = null;

    /**
     * Icon validator instance.
     */
    protected ?IconValidator $iconValidator = null;

    /**
     * Whether icon validation is enabled.
     */
    protected bool $validateIcons = true;

    public function __construct(?object $entitlements = null, ?IconValidator $iconValidator = null)
    {
        if ($entitlements === null && class_exists(EntitlementService::class)) {
            $this->entitlements = app(EntitlementService::class);
        } else {
            $this->entitlements = $entitlements;
        }

        $this->iconValidator = $iconValidator ?? new IconValidator;
        $this->cacheTtl = (int) config('core.admin_menu.cache_ttl', self::DEFAULT_CACHE_TTL);
        $this->cachingEnabled = (bool) config('core.admin_menu.cache_enabled', true);
        $this->validateIcons = (bool) config('core.admin_menu.validate_icons', true);
    }

    /**
     * Register a menu provider.
     */
    public function register(AdminMenuProvider $provider): void
    {
        $this->providers[] = $provider;

        // Also register as dynamic provider if it implements the interface
        if ($provider instanceof DynamicMenuProvider) {
            $this->dynamicProviders[] = $provider;
        }
    }

    /**
     * Register a dynamic menu provider.
     */
    public function registerDynamic(DynamicMenuProvider $provider): void
    {
        $this->dynamicProviders[] = $provider;
    }

    /**
     * Enable or disable caching.
     */
    public function setCachingEnabled(bool $enabled): void
    {
        $this->cachingEnabled = $enabled;
    }

    /**
     * Set cache TTL in seconds.
     */
    public function setCacheTtl(int $seconds): void
    {
        $this->cacheTtl = $seconds;
    }

    /**
     * Build the complete menu structure.
     *
     * @param  object|null  $workspace  Current workspace for entitlement checks (Workspace model instance)
     * @param  bool  $isAdmin  Whether user is admin (Hades)
     * @param  object|null  $user  The authenticated user for permission checks (User model instance)
     * @return array<int, array>
     */
    public function build(?object $workspace, bool $isAdmin = false, ?object $user = null): array
    {
        // Get static items (potentially cached)
        $staticItems = $this->getStaticItems($workspace, $isAdmin, $user);

        // Get dynamic items (never cached)
        $dynamicItems = $this->getDynamicItems($workspace, $isAdmin, $user);

        // Merge static and dynamic items
        $allItems = $this->mergeItems($staticItems, $dynamicItems);

        // Build the menu structure
        return $this->buildMenuStructure($allItems, $workspace, $isAdmin);
    }

    /**
     * Get static menu items, using cache if enabled.
     *
     * @param  object|null  $workspace  Workspace model instance
     * @param  object|null  $user  User model instance
     * @return array<string, array<int, array{priority: int, item: \Closure}>>
     */
    protected function getStaticItems(?object $workspace, bool $isAdmin, ?object $user): array
    {
        if (! $this->cachingEnabled) {
            return $this->collectItems($workspace, $isAdmin, $user);
        }

        $cacheKey = $this->buildCacheKey($workspace, $isAdmin, $user);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($workspace, $isAdmin, $user) {
            return $this->collectItems($workspace, $isAdmin, $user);
        });
    }

    /**
     * Get dynamic menu items from dynamic providers.
     *
     * @param  object|null  $workspace  Workspace model instance
     * @param  object|null  $user  User model instance
     * @return array<string, array<int, array{priority: int, item: \Closure}>>
     */
    protected function getDynamicItems(?object $workspace, bool $isAdmin, ?object $user): array
    {
        $grouped = [];

        foreach ($this->dynamicProviders as $provider) {
            $items = $provider->dynamicMenuItems($user, $workspace, $isAdmin);

            foreach ($items as $registration) {
                $group = $registration['group'] ?? 'services';
                $entitlement = $registration['entitlement'] ?? null;
                $requiresAdmin = $registration['admin'] ?? false;
                $permissions = $registration['permissions'] ?? [];

                // Skip if requires admin and user isn't admin
                if ($requiresAdmin && ! $isAdmin) {
                    continue;
                }

                // Skip if entitlement check fails
                if ($entitlement && $workspace && $this->entitlements !== null) {
                    if ($this->entitlements->can($workspace, $entitlement)->isDenied()) {
                        continue;
                    }
                }

                // Skip if no workspace and entitlement required
                if ($entitlement && ! $workspace) {
                    continue;
                }

                // Skip if permission check fails
                if (! empty($permissions) && ! $this->checkPermissions($user, $permissions, $workspace)) {
                    continue;
                }

                $grouped[$group][] = [
                    'priority' => $registration['priority'] ?? 50,
                    'item' => $registration['item'],
                    'dynamic' => true,
                ];
            }
        }

        return $grouped;
    }

    /**
     * Merge static and dynamic items.
     *
     * @param  array<string, array>  $static
     * @param  array<string, array>  $dynamic
     * @return array<string, array>
     */
    protected function mergeItems(array $static, array $dynamic): array
    {
        foreach ($dynamic as $group => $items) {
            if (! isset($static[$group])) {
                $static[$group] = [];
            }
            $static[$group] = array_merge($static[$group], $items);
        }

        return $static;
    }

    /**
     * Build the final menu structure from collected items.
     *
     * @param  object|null  $workspace  Workspace model instance
     */
    protected function buildMenuStructure(array $allItems, ?object $workspace, bool $isAdmin): array
    {
        // Build flat structure with dividers
        $menu = [];
        $firstGroup = true;

        foreach ($this->groups as $groupKey => $groupConfig) {
            // Skip admin group unless user is admin AND on system workspace
            if ($groupKey === 'admin' && (! $isAdmin || $workspace?->slug !== 'system')) {
                continue;
            }

            $groupItems = $allItems[$groupKey] ?? [];

            if (empty($groupItems)) {
                continue;
            }

            // Sort by priority
            usort($groupItems, fn ($a, $b) => $a['priority'] <=> $b['priority']);

            // Evaluate closures and extract items
            $evaluatedItems = [];
            foreach ($groupItems as $item) {
                $evaluated = ($item['item'])();
                if ($evaluated !== null) {
                    $evaluatedItems[] = $evaluated;
                }
            }

            if (empty($evaluatedItems)) {
                continue;
            }

            // Add divider before non-first groups
            if (! $firstGroup) {
                $menu[] = ['divider' => true];
            }
            $firstGroup = false;

            // Standalone groups add items directly
            if (! empty($groupConfig['standalone'])) {
                foreach ($evaluatedItems as $item) {
                    $menu[] = $item;
                }

                continue;
            }

            // Other groups become dropdown parents
            // Check if any item is active
            $isActive = collect($evaluatedItems)->contains(fn ($item) => $item['active'] ?? false);

            // Flatten children: each item becomes a child entry
            $children = [];
            foreach ($evaluatedItems as $item) {
                if (! empty($item['children'])) {
                    // Item has its own children - add label as section header then children
                    $children[] = [
                        'section' => $item['label'],
                        'icon' => $item['icon'] ?? null,
                        'color' => $item['color'] ?? null,
                    ];
                    foreach ($item['children'] as $child) {
                        $children[] = $child;
                    }
                } else {
                    // Item is a direct link - preserve icon and color
                    $children[] = [
                        'label' => $item['label'],
                        'href' => $item['href'] ?? '#',
                        'icon' => $item['icon'] ?? null,
                        'color' => $item['color'] ?? null,
                        'active' => $item['active'] ?? false,
                        'badge' => $item['badge'] ?? null,
                    ];
                }
            }

            $menu[] = [
                'label' => $groupConfig['label'],
                'icon' => $groupConfig['icon'],
                'color' => $groupConfig['color'],
                'active' => $isActive,
                'children' => $children,
            ];
        }

        return $menu;
    }

    /**
     * Build the cache key for menu items.
     *
     * @param  object|null  $workspace  Workspace model instance
     * @param  object|null  $user  User model instance
     */
    protected function buildCacheKey(?object $workspace, bool $isAdmin, ?object $user): string
    {
        $parts = [
            self::CACHE_PREFIX,
            'w'.($workspace?->id ?? 'null'),
            'a'.($isAdmin ? '1' : '0'),
            'u'.($user?->id ?? 'null'),
        ];

        // Add dynamic cache key modifiers
        foreach ($this->dynamicProviders as $provider) {
            $dynamicKey = $provider->dynamicCacheKey($user, $workspace);
            if ($dynamicKey !== null) {
                $parts[] = md5($dynamicKey);
            }
        }

        return implode(':', $parts);
    }

    /**
     * Collect items from all providers, filtering by entitlements and permissions.
     *
     * @param  object|null  $workspace  Workspace model instance
     * @param  object|null  $user  User model instance
     * @return array<string, array<int, array{priority: int, item: \Closure}>>
     */
    protected function collectItems(?object $workspace, bool $isAdmin, ?object $user): array
    {
        $grouped = [];

        foreach ($this->providers as $provider) {
            // Check provider-level permissions first
            if (! $provider->canViewMenu($user, $workspace)) {
                continue;
            }

            foreach ($provider->adminMenuItems() as $registration) {
                $group = $registration['group'] ?? 'services';
                $entitlement = $registration['entitlement'] ?? null;
                $requiresAdmin = $registration['admin'] ?? false;
                $permissions = $registration['permissions'] ?? [];

                // Skip if requires admin and user isn't admin
                if ($requiresAdmin && ! $isAdmin) {
                    continue;
                }

                // Skip if entitlement check fails
                if ($entitlement && $workspace && $this->entitlements !== null) {
                    if ($this->entitlements->can($workspace, $entitlement)->isDenied()) {
                        continue;
                    }
                }

                // Skip if no workspace and entitlement required
                if ($entitlement && ! $workspace) {
                    continue;
                }

                // Skip if item-level permission check fails
                if (! empty($permissions) && ! $this->checkPermissions($user, $permissions, $workspace)) {
                    continue;
                }

                $grouped[$group][] = [
                    'priority' => $registration['priority'] ?? 50,
                    'item' => $registration['item'],
                ];
            }
        }

        return $grouped;
    }

    /**
     * Check if a user has all required permissions.
     *
     * @param  object|null  $user  User model instance
     * @param  array<string>  $permissions
     * @param  object|null  $workspace  Workspace model instance
     */
    protected function checkPermissions(?object $user, array $permissions, ?object $workspace): bool
    {
        if (empty($permissions)) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        foreach ($permissions as $permission) {
            // Check using Laravel's authorization
            if (method_exists($user, 'can') && ! $user->can($permission, $workspace)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Invalidate cached menu for a specific context.
     *
     * @param  object|null  $workspace  Workspace model instance
     * @param  object|null  $user  User model instance
     */
    public function invalidateCache(?object $workspace = null, ?object $user = null): void
    {
        if ($workspace !== null && $user !== null) {
            // Invalidate specific cache keys
            foreach ([true, false] as $isAdmin) {
                $cacheKey = $this->buildCacheKey($workspace, $isAdmin, $user);
                Cache::forget($cacheKey);
            }
        } else {
            // Flush all admin menu caches using tags if available
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags([self::CACHE_PREFIX])->flush();
            }
        }
    }

    /**
     * Invalidate all cached menus for a workspace.
     *
     * @param  object  $workspace  Workspace model instance
     */
    public function invalidateWorkspaceCache(object $workspace): void
    {
        // We can't easily clear pattern-based cache keys with all drivers,
        // so we rely on TTL expiration for non-tagged caches
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags([self::CACHE_PREFIX, 'workspace:'.$workspace->id])->flush();
        }
    }

    /**
     * Invalidate all cached menus for a user.
     *
     * @param  object  $user  User model instance
     */
    public function invalidateUserCache(object $user): void
    {
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags([self::CACHE_PREFIX, 'user:'.$user->id])->flush();
        }
    }

    /**
     * Get available group keys.
     *
     * @return array<string>
     */
    public function getGroups(): array
    {
        return array_keys($this->groups);
    }

    /**
     * Get group configuration.
     *
     * @return array<string, array>
     */
    public function getGroupConfig(string $key): array
    {
        return $this->groups[$key] ?? [];
    }

    /**
     * Get the icon validator instance.
     */
    public function getIconValidator(): IconValidator
    {
        return $this->iconValidator;
    }

    /**
     * Enable or disable icon validation.
     */
    public function setIconValidation(bool $enabled): void
    {
        $this->validateIcons = $enabled;
    }

    /**
     * Validate an icon and return whether it's valid.
     *
     * @param  string  $icon  The icon name to validate
     * @return bool True if valid, false otherwise
     */
    public function validateIcon(string $icon): bool
    {
        if (! $this->validateIcons || $this->iconValidator === null) {
            return true;
        }

        return $this->iconValidator->isValid($icon);
    }

    /**
     * Validate a menu item's icon.
     *
     * @param  array  $item  The menu item array
     * @return array<string> Array of validation error messages (empty if valid)
     */
    public function validateMenuItem(array $item): array
    {
        $errors = [];

        if (! $this->validateIcons || $this->iconValidator === null) {
            return $errors;
        }

        $icon = $item['icon'] ?? null;
        if ($icon !== null && ! empty($icon)) {
            $iconErrors = $this->iconValidator->validate($icon);
            $errors = array_merge($errors, $iconErrors);
        }

        // Validate children icons if present
        if (! empty($item['children'])) {
            foreach ($item['children'] as $index => $child) {
                $childIcon = $child['icon'] ?? null;
                if ($childIcon !== null && ! empty($childIcon)) {
                    $childErrors = $this->iconValidator->validate($childIcon);
                    foreach ($childErrors as $error) {
                        $errors[] = "Child item {$index}: {$error}";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Get all service menu items indexed by service key.
     *
     * @param  object|null  $workspace  Current workspace for entitlement checks (Workspace model instance)
     * @param  bool  $isAdmin  Whether user is admin (Hades)
     * @param  object|null  $user  The authenticated user for permission checks (User model instance)
     * @return array<string, array> Service items indexed by service key
     */
    public function getAllServiceItems(?object $workspace, bool $isAdmin = false, ?object $user = null): array
    {
        $services = [];

        foreach ($this->providers as $provider) {
            // Check provider-level permissions
            if (! $provider->canViewMenu($user, $workspace)) {
                continue;
            }

            foreach ($provider->adminMenuItems() as $registration) {
                if (($registration['group'] ?? 'services') !== 'services') {
                    continue;
                }

                $serviceKey = $registration['service'] ?? null;
                if (! $serviceKey) {
                    continue;
                }

                $entitlement = $registration['entitlement'] ?? null;
                $requiresAdmin = $registration['admin'] ?? false;
                $permissions = $registration['permissions'] ?? [];

                // Skip if requires admin and user isn't admin
                if ($requiresAdmin && ! $isAdmin) {
                    continue;
                }

                // Skip if entitlement check fails
                if ($entitlement && $workspace && $this->entitlements !== null) {
                    if ($this->entitlements->can($workspace, $entitlement)->isDenied()) {
                        continue;
                    }
                }

                // Skip if permission check fails
                if (! empty($permissions) && ! $this->checkPermissions($user, $permissions, $workspace)) {
                    continue;
                }

                // Evaluate the closure and store by service key
                $item = ($registration['item'])();
                if ($item) {
                    $services[$serviceKey] = array_merge($item, [
                        'priority' => $registration['priority'] ?? 50,
                    ]);
                }
            }
        }

        // Sort by priority
        uasort($services, fn ($a, $b) => ($a['priority'] ?? 50) <=> ($b['priority'] ?? 50));

        return $services;
    }

    /**
     * Get a specific service's menu item including its children (tabs).
     *
     * @param  string  $serviceKey  The service identifier (e.g., 'commerce', 'support')
     * @param  object|null  $workspace  Current workspace for entitlement checks (Workspace model instance)
     * @param  bool  $isAdmin  Whether user is admin (Hades)
     * @param  object|null  $user  The authenticated user for permission checks (User model instance)
     * @return array|null The service menu item with children, or null if not found
     */
    public function getServiceItem(string $serviceKey, ?object $workspace, bool $isAdmin = false, ?object $user = null): ?array
    {
        foreach ($this->providers as $provider) {
            // Check provider-level permissions
            if (! $provider->canViewMenu($user, $workspace)) {
                continue;
            }

            foreach ($provider->adminMenuItems() as $registration) {
                // Only check services group items with matching service key
                if (($registration['group'] ?? 'services') !== 'services') {
                    continue;
                }

                if (($registration['service'] ?? null) !== $serviceKey) {
                    continue;
                }

                $entitlement = $registration['entitlement'] ?? null;
                $requiresAdmin = $registration['admin'] ?? false;
                $permissions = $registration['permissions'] ?? [];

                // Skip if requires admin and user isn't admin
                if ($requiresAdmin && ! $isAdmin) {
                    continue;
                }

                // Skip if entitlement check fails
                if ($entitlement && $workspace && $this->entitlements !== null) {
                    if ($this->entitlements->can($workspace, $entitlement)->isDenied()) {
                        continue;
                    }
                }

                // Skip if permission check fails
                if (! empty($permissions) && ! $this->checkPermissions($user, $permissions, $workspace)) {
                    continue;
                }

                // Evaluate the closure and return the item
                $item = ($registration['item'])();

                return $item;
            }
        }

        return null;
    }
}
