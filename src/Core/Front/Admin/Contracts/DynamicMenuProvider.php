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
 * Interface for providers that supply dynamic (uncached) menu items.
 *
 * Dynamic menu items are computed at runtime based on context and are never
 * cached. Use this interface when menu items need to reflect real-time data
 * that changes frequently or per-request.
 *
 * ## When to Use DynamicMenuProvider
 *
 * - **Notification counts** - Unread messages, pending approvals
 * - **Recent items** - Recently accessed documents, pages
 * - **User-specific content** - Personalized shortcuts, favorites
 * - **Real-time status** - Online users, active sessions
 *
 * ## Performance Considerations
 *
 * Dynamic items are computed on every request, so keep the `dynamicMenuItems()`
 * method efficient:
 *
 * - Use eager loading for database queries
 * - Cache intermediate results if possible
 * - Limit the number of items returned
 *
 * ## Cache Integration
 *
 * Static menu items from `AdminMenuProvider` are cached. Dynamic items are
 * merged in after cache retrieval. The `dynamicCacheKey()` method can be used
 * to invalidate the static cache when dynamic state changes significantly.
 *
 *
 * @see AdminMenuProvider For static (cached) menu items
 */
interface DynamicMenuProvider
{
    /**
     * Get dynamic menu items that should not be cached.
     *
     * Called on every request to retrieve menu items that depend on
     * real-time data. Keep this method efficient as it runs uncached.
     *
     * Each item should include the same structure as AdminMenuProvider::adminMenuItems()
     * plus an optional 'dynamic' key set to true for identification.
     *
     * @param  object|null  $user  The authenticated user (User model instance)
     * @param  object|null  $workspace  The current workspace context (Workspace model instance)
     * @param  bool  $isAdmin  Whether the user is an admin
     * @return array<int, array{
     *     group: string,
     *     priority: int,
     *     entitlement?: string|null,
     *     permissions?: array<string>|null,
     *     admin?: bool,
     *     dynamic?: bool,
     *     item: \Closure
     * }>
     */
    public function dynamicMenuItems(?object $user, ?object $workspace, bool $isAdmin): array;

    /**
     * Get the cache key modifier for dynamic items.
     *
     * Dynamic items from this provider will invalidate menu cache when
     * this key changes. Return null if dynamic items should never affect
     * cache invalidation.
     *
     * @param  object|null  $user  User model instance
     * @param  object|null  $workspace  Workspace model instance
     */
    public function dynamicCacheKey(?object $user, ?object $workspace): ?string;
}
