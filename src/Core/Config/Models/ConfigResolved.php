<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\Models;

use Core\Config\ConfigResult;
use Core\Config\Enums\ConfigType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Materialised config resolution.
 *
 * This is the READ table - all config lookups hit this directly.
 * No computation at read time, just indexed lookup.
 *
 * Prime operation populates this table by running full resolution.
 *
 * Note: workspace_id and channel_id use 0 for "null/system" scope
 * due to MariaDB composite unique constraint limitations.
 *
 * @property int $workspace_id 0 = system scope
 * @property int $channel_id 0 = all channels
 * @property string $key_code
 * @property mixed $value
 * @property string $type
 * @property bool $locked
 * @property int|null $source_profile_id
 * @property int|null $source_channel_id
 * @property bool $virtual
 * @property \Carbon\Carbon $computed_at
 */
class ConfigResolved extends Model
{
    protected $table = 'config_resolved';

    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'channel_id',
        'key_code',
        'value',
        'type',
        'locked',
        'source_profile_id',
        'source_channel_id',
        'virtual',
        'computed_at',
    ];

    protected $casts = [
        'value' => 'json',
        'locked' => 'boolean',
        'virtual' => 'boolean',
        'computed_at' => 'datetime',
    ];

    /**
     * Workspace this resolution is for (null = system).
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
     * Channel this resolution is for (null = all channels).
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Profile that provided this value.
     */
    public function sourceProfile(): BelongsTo
    {
        return $this->belongsTo(ConfigProfile::class, 'source_profile_id');
    }

    /**
     * Get the resolved value with proper type casting.
     */
    public function getTypedValue(): mixed
    {
        $type = ConfigType::tryFrom($this->type) ?? ConfigType::STRING;

        return $type->cast($this->value);
    }

    /**
     * Convert to ConfigResult for API compatibility.
     */
    public function toResult(): ConfigResult
    {
        $type = ConfigType::tryFrom($this->type) ?? ConfigType::STRING;

        if ($this->virtual) {
            return ConfigResult::virtual(
                key: $this->key_code,
                value: $this->value,
                type: $type,
            );
        }

        // Determine scope type from source profile
        $scopeType = null;
        if ($this->source_profile_id !== null) {
            $scopeType = $this->sourceProfile?->scope_type;
        }

        return new ConfigResult(
            key: $this->key_code,
            value: $type->cast($this->value),
            type: $type,
            found: true,
            locked: $this->locked,
            virtual: $this->virtual,
            resolvedFrom: $scopeType,
            profileId: $this->source_profile_id,
            channelId: $this->source_channel_id,
        );
    }

    /**
     * Look up a resolved config value.
     *
     * This is THE read path - single indexed lookup.
     */
    public static function lookup(string $keyCode, ?int $workspaceId = null, ?int $channelId = null): ?self
    {
        return static::where('workspace_id', $workspaceId)
            ->where('channel_id', $channelId)
            ->where('key_code', $keyCode)
            ->first();
    }

    /**
     * Get all resolved config for a scope.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function forScope(?int $workspaceId = null, ?int $channelId = null): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('workspace_id', $workspaceId)
            ->where('channel_id', $channelId)
            ->get();
    }

    /**
     * Store a resolved config value.
     */
    public static function store(
        string $keyCode,
        mixed $value,
        ConfigType $type,
        ?int $workspaceId = null,
        ?int $channelId = null,
        bool $locked = false,
        ?int $sourceProfileId = null,
        ?int $sourceChannelId = null,
        bool $virtual = false,
    ): self {
        return static::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'channel_id' => $channelId,
                'key_code' => $keyCode,
            ],
            [
                'value' => $value,
                'type' => $type->value,
                'locked' => $locked,
                'source_profile_id' => $sourceProfileId,
                'source_channel_id' => $sourceChannelId,
                'virtual' => $virtual,
                'computed_at' => now(),
            ]
        );
    }

    /**
     * Clear resolved config for a scope.
     */
    public static function clearScope(?int $workspaceId = null, ?int $channelId = null): int
    {
        return static::where('workspace_id', $workspaceId)
            ->where('channel_id', $channelId)
            ->delete();
    }

    /**
     * Clear all resolved config for a workspace (all channels).
     */
    public static function clearWorkspace(?int $workspaceId = null): int
    {
        return static::where('workspace_id', $workspaceId)->delete();
    }

    /**
     * Clear resolved config for a specific key across all scopes.
     */
    public static function clearKey(string $keyCode): int
    {
        return static::where('key_code', $keyCode)->delete();
    }

    /**
     * Composite key handling for Eloquent.
     */
    protected function setKeysForSaveQuery($query)
    {
        $query->where('workspace_id', $this->workspace_id)
            ->where('channel_id', $this->channel_id)
            ->where('key_code', $this->key_code);

        return $query;
    }
}
