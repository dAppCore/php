<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer\Gate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Action permission record.
 *
 * Represents a whitelisted action that users with specific roles/guards
 * are permitted to perform.
 *
 * @property int $id
 * @property string $action Action identifier (e.g., 'product.create')
 * @property string|null $scope Resource scope (type or specific ID)
 * @property string $guard Guard name ('web', 'api', 'admin')
 * @property string|null $role Required role or null for any authenticated user
 * @property bool $allowed Whether this action is permitted
 * @property string $source How this was created ('trained', 'seeded', 'manual')
 * @property string|null $trained_route The route used during training
 * @property int|null $trained_by User ID who trained this action
 * @property \Carbon\Carbon|null $trained_at When training occurred
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ActionPermission extends Model
{
    protected $table = 'core_action_permissions';

    protected $fillable = [
        'action',
        'scope',
        'guard',
        'role',
        'allowed',
        'source',
        'trained_route',
        'trained_by',
        'trained_at',
    ];

    protected $casts = [
        'allowed' => 'boolean',
        'trained_at' => 'datetime',
    ];

    /**
     * Source constants.
     */
    public const SOURCE_TRAINED = 'trained';

    public const SOURCE_SEEDED = 'seeded';

    public const SOURCE_MANUAL = 'manual';

    /**
     * User who trained this permission.
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'trained_by');
    }

    /**
     * Check if action is allowed for the given context.
     */
    public static function isAllowed(
        string $action,
        string $guard = 'web',
        ?string $role = null,
        ?string $scope = null
    ): bool {
        $query = static::query()
            ->where('action', $action)
            ->where('guard', $guard)
            ->where('allowed', true);

        // Check scope match (null matches any, or exact match)
        if ($scope !== null) {
            $query->where(function ($q) use ($scope) {
                $q->whereNull('scope')
                    ->orWhere('scope', $scope);
            });
        }

        // Check role match (null role in permission = any authenticated)
        if ($role !== null) {
            $query->where(function ($q) use ($role) {
                $q->whereNull('role')
                    ->orWhere('role', $role);
            });
        } else {
            // No role provided, only match null role permissions
            $query->whereNull('role');
        }

        return $query->exists();
    }

    /**
     * Find or create a permission for the given action context.
     */
    public static function findOrCreateFor(
        string $action,
        string $guard = 'web',
        ?string $role = null,
        ?string $scope = null
    ): self {
        return static::firstOrCreate(
            [
                'action' => $action,
                'guard' => $guard,
                'role' => $role,
                'scope' => $scope,
            ],
            [
                'allowed' => false,
                'source' => self::SOURCE_MANUAL,
            ]
        );
    }

    /**
     * Train (allow) an action.
     */
    public static function train(
        string $action,
        string $guard = 'web',
        ?string $role = null,
        ?string $scope = null,
        ?string $route = null,
        ?int $trainedBy = null
    ): self {
        $permission = static::findOrCreateFor($action, $guard, $role, $scope);

        $permission->update([
            'allowed' => true,
            'source' => self::SOURCE_TRAINED,
            'trained_route' => $route,
            'trained_by' => $trainedBy,
            'trained_at' => now(),
        ]);

        return $permission;
    }

    /**
     * Revoke an action permission.
     */
    public static function revoke(
        string $action,
        string $guard = 'web',
        ?string $role = null,
        ?string $scope = null
    ): bool {
        return static::query()
            ->where('action', $action)
            ->where('guard', $guard)
            ->where('role', $role)
            ->where('scope', $scope)
            ->update(['allowed' => false]) > 0;
    }

    /**
     * Get all actions for a guard.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function forGuard(string $guard): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('guard', $guard)->get();
    }

    /**
     * Get all allowed actions for a guard/role combination.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function allowedFor(string $guard, ?string $role = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::where('guard', $guard)
            ->where('allowed', true);

        if ($role !== null) {
            $query->where(function ($q) use ($role) {
                $q->whereNull('role')
                    ->orWhere('role', $role);
            });
        } else {
            $query->whereNull('role');
        }

        return $query->get();
    }
}
