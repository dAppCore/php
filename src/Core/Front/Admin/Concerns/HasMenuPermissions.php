<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Admin\Concerns;

/**
 * Provides default permission handling for AdminMenuProvider implementations.
 *
 * Include this trait in classes that implement AdminMenuProvider to get
 * sensible default behaviour for permission checks. Override methods
 * as needed for custom permission logic.
 */
trait HasMenuPermissions
{
    /**
     * Get the permissions required to view any menu items from this provider.
     *
     * Override this method to specify required permissions.
     *
     * @return array<string>
     */
    public function menuPermissions(): array
    {
        return [];
    }

    /**
     * Check if the user has permission to view menu items from this provider.
     *
     * By default, checks that the user has all permissions returned by
     * menuPermissions(). Override for custom logic.
     *
     * @param  object|null  $user  The authenticated user (User model instance)
     * @param  object|null  $workspace  The current workspace context (Workspace model instance)
     */
    public function canViewMenu(?object $user, ?object $workspace): bool
    {
        // No user means no permission (unless we have no requirements)
        $permissions = $this->menuPermissions();

        if (empty($permissions)) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        // Check each required permission
        foreach ($permissions as $permission) {
            if (! $this->userHasPermission($user, $permission, $workspace)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a user has a specific permission.
     *
     * Override this method to customise how permission checks are performed.
     * By default, uses Laravel's Gate/Authorization system.
     *
     * @param  object  $user  User model instance
     * @param  object|null  $workspace  Workspace model instance
     */
    protected function userHasPermission(object $user, string $permission, ?object $workspace): bool
    {
        // Check using Laravel's authorization
        if (method_exists($user, 'can')) {
            return $user->can($permission, $workspace);
        }

        // Fallback: check for hasPermission method (common in permission packages)
        if (method_exists($user, 'hasPermission')) {
            return $user->hasPermission($permission);
        }

        // Fallback: check for hasPermissionTo method (Spatie Permission)
        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo($permission);
        }

        // No permission system found, allow by default
        return true;
    }
}
