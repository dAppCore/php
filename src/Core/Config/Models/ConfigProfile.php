<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\Models;

use Core\Config\Enums\ScopeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Configuration profile (M2 layer).
 *
 * Groups config values at a specific scope level.
 * Profiles can inherit from parent profiles.
 *
 * @property int $id
 * @property string $name
 * @property ScopeType $scope_type
 * @property int|null $scope_id
 * @property int|null $parent_profile_id
 * @property int $priority
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class ConfigProfile extends Model
{
    use SoftDeletes;

    protected $table = 'config_profiles';

    protected $fillable = [
        'name',
        'scope_type',
        'scope_id',
        'parent_profile_id',
        'priority',
    ];

    protected $casts = [
        'scope_type' => ScopeType::class,
        'priority' => 'integer',
    ];

    /**
     * Parent profile (for profile-level inheritance).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_profile_id');
    }

    /**
     * Child profiles.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_profile_id');
    }

    /**
     * Config values in this profile.
     */
    public function values(): HasMany
    {
        return $this->hasMany(ConfigValue::class, 'profile_id');
    }

    /**
     * Get system profile.
     */
    public static function system(): ?self
    {
        return static::where('scope_type', ScopeType::SYSTEM)
            ->whereNull('scope_id')
            ->orderByDesc('priority')
            ->first();
    }

    /**
     * Get profiles for a scope.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function forScope(ScopeType $type, ?int $scopeId = null): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('scope_type', $type)
            ->where('scope_id', $scopeId)
            ->orderByDesc('priority')
            ->get();
    }

    /**
     * Get profile for workspace.
     */
    public static function forWorkspace(int $workspaceId): ?self
    {
        return static::where('scope_type', ScopeType::WORKSPACE)
            ->where('scope_id', $workspaceId)
            ->orderByDesc('priority')
            ->first();
    }

    /**
     * Get or create system profile.
     */
    public static function ensureSystem(): self
    {
        return static::firstOrCreate(
            [
                'scope_type' => ScopeType::SYSTEM,
                'scope_id' => null,
            ],
            [
                'name' => 'System Default',
                'priority' => 0,
            ]
        );
    }

    /**
     * Get or create workspace profile.
     */
    public static function ensureWorkspace(int $workspaceId, ?int $parentProfileId = null): self
    {
        return static::firstOrCreate(
            [
                'scope_type' => ScopeType::WORKSPACE,
                'scope_id' => $workspaceId,
            ],
            [
                'name' => "Workspace {$workspaceId}",
                'parent_profile_id' => $parentProfileId,
                'priority' => 0,
            ]
        );
    }
}
