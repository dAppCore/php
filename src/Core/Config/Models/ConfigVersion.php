<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuration version (snapshot).
 *
 * Stores a point-in-time snapshot of all config values for a scope.
 * Used for version history and rollback capability.
 *
 * @property int $id
 * @property int $profile_id
 * @property int|null $workspace_id
 * @property string $label
 * @property string $snapshot
 * @property string|null $author
 * @property \Carbon\Carbon $created_at
 */
class ConfigVersion extends Model
{
    protected $table = 'config_versions';

    /**
     * Disable updated_at timestamp since versions are immutable.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'profile_id',
        'workspace_id',
        'label',
        'snapshot',
        'author',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * The profile this version belongs to.
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ConfigProfile::class, 'profile_id');
    }

    /**
     * Workspace this version is for (null = system).
     *
     * Requires Core\Tenant module to be installed.
     */
    public function workspace(): BelongsTo
    {
        if (class_exists(\Core\Tenant\Models\Workspace::class)) {
            return $this->belongsTo(\Core\Tenant\Models\Workspace::class);
        }

        // Return a null relationship when Tenant module is not installed
        return $this->belongsTo(self::class, 'workspace_id')->whereRaw('1 = 0');
    }

    /**
     * Get the parsed snapshot data.
     *
     * @return array<string, mixed>
     */
    public function getSnapshotData(): array
    {
        return json_decode($this->snapshot, true) ?? [];
    }

    /**
     * Get the config values from the snapshot.
     *
     * @return array<array{key: string, value: mixed, locked: bool}>
     */
    public function getValues(): array
    {
        $data = $this->getSnapshotData();

        return $data['values'] ?? [];
    }

    /**
     * Get a specific value from the snapshot.
     *
     * @param  string  $key  Config key code
     * @return mixed|null The value or null if not found
     */
    public function getValue(string $key): mixed
    {
        $values = $this->getValues();

        foreach ($values as $value) {
            if ($value['key'] === $key) {
                return $value['value'];
            }
        }

        return null;
    }

    /**
     * Check if a key exists in the snapshot.
     *
     * @param  string  $key  Config key code
     */
    public function hasKey(string $key): bool
    {
        $values = $this->getValues();

        foreach ($values as $value) {
            if ($value['key'] === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get versions for a scope.
     *
     * @param  int|null  $workspaceId  Workspace ID or null for system
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function forScope(?int $workspaceId = null): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get the latest version for a scope.
     *
     * @param  int|null  $workspaceId  Workspace ID or null for system
     */
    public static function latest(?int $workspaceId = null): ?self
    {
        return static::where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Get versions created by a specific author.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function byAuthor(string $author): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('author', $author)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get versions created within a date range.
     *
     * @param  \Carbon\Carbon  $from  Start date
     * @param  \Carbon\Carbon  $to  End date
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function inDateRange(\Carbon\Carbon $from, \Carbon\Carbon $to): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->get();
    }
}
