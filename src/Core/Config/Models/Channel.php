<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

/**
 * Configuration channel (voice/context substrate).
 *
 * Channels represent the context in which config is resolved:
 * - Technical: web, api, mobile
 * - Social: instagram, twitter, tiktok
 * - Voice: vi, support, formal
 *
 * Channels can be hierarchical (instagram inherits from social).
 * System channels (workspace_id = null) are available to all.
 * Workspace channels are private to that workspace.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $parent_id
 * @property int|null $workspace_id
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Channel extends Model
{
    protected $table = 'config_channels';

    protected $fillable = [
        'code',
        'name',
        'parent_id',
        'workspace_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Parent channel (for inheritance).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Child channels.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Workspace this channel belongs to (null = system channel).
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
     * Config values for this channel.
     */
    public function values(): HasMany
    {
        return $this->hasMany(ConfigValue::class, 'channel_id');
    }

    /**
     * Find channel by code.
     */
    public static function byCode(string $code, ?int $workspaceId = null): ?self
    {
        return static::where('code', $code)
            ->where(function ($query) use ($workspaceId) {
                $query->whereNull('workspace_id');
                if ($workspaceId !== null) {
                    $query->orWhere('workspace_id', $workspaceId);
                }
            })
            ->orderByRaw('workspace_id IS NULL') // Workspace-specific first
            ->first();
    }

    /**
     * Build inheritance chain (most specific to least).
     *
     * Includes cycle detection to prevent infinite loops from data corruption.
     *
     * @return Collection<int, self>
     */
    public function inheritanceChain(): Collection
    {
        $chain = new Collection([$this]);
        $current = $this;
        $seen = [$this->id => true];

        while ($current->parent_id !== null) {
            if (isset($seen[$current->parent_id])) {
                Log::error('Circular reference detected in channel inheritance', [
                    'channel_id' => $this->id,
                    'cycle_at' => $current->parent_id,
                ]);
                break;
            }

            $parent = $current->parent;
            if ($parent === null) {
                break;
            }

            $seen[$parent->id] = true;
            $chain->push($parent);
            $current = $parent;
        }

        return $chain;
    }

    /**
     * Get all channel codes in inheritance chain.
     *
     * @return array<string>
     */
    public function inheritanceCodes(): array
    {
        return $this->inheritanceChain()->pluck('code')->all();
    }

    /**
     * Check if this channel inherits from another.
     */
    public function inheritsFrom(string $code): bool
    {
        return in_array($code, $this->inheritanceCodes(), true);
    }

    /**
     * Is this a system channel (available to all workspaces)?
     */
    public function isSystem(): bool
    {
        return $this->workspace_id === null;
    }

    /**
     * Get metadata value.
     */
    public function meta(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Ensure a channel exists.
     */
    public static function ensure(
        string $code,
        string $name,
        ?string $parentCode = null,
        ?int $workspaceId = null,
        ?array $metadata = null,
    ): self {
        $parentId = null;
        if ($parentCode !== null) {
            $parent = static::byCode($parentCode, $workspaceId);
            $parentId = $parent?->id;
        }

        return static::firstOrCreate(
            [
                'code' => $code,
                'workspace_id' => $workspaceId,
            ],
            [
                'name' => $name,
                'parent_id' => $parentId,
                'metadata' => $metadata,
            ]
        );
    }
}
