<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\Models;

use Core\Config\Enums\ConfigType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configuration key definition (M1 layer).
 *
 * Defines what configuration keys exist and their metadata.
 * Keys use dot-notation: cdn.bunny.api_key, social.posting.enabled
 *
 * @property int $id
 * @property string $code
 * @property int|null $parent_id
 * @property ConfigType $type
 * @property string $category
 * @property string|null $description
 * @property mixed $default_value
 * @property bool $is_sensitive
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ConfigKey extends Model
{
    protected $table = 'config_keys';

    protected $fillable = [
        'code',
        'parent_id',
        'type',
        'category',
        'description',
        'default_value',
        'is_sensitive',
    ];

    protected $casts = [
        'type' => ConfigType::class,
        'default_value' => 'json',
        'is_sensitive' => 'boolean',
    ];

    /**
     * Check if this key contains sensitive data that should be encrypted.
     */
    public function isSensitive(): bool
    {
        return $this->is_sensitive ?? false;
    }

    /**
     * Parent key (for hierarchical grouping).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Child keys.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Values assigned to this key across profiles.
     */
    public function values(): HasMany
    {
        return $this->hasMany(ConfigValue::class, 'key_id');
    }

    /**
     * Get typed default value.
     */
    public function getTypedDefault(): mixed
    {
        if ($this->default_value === null) {
            return $this->type->default();
        }

        return $this->type->cast($this->default_value);
    }

    /**
     * Find key by code.
     */
    public static function byCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    /**
     * Get all keys for a category.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function forCategory(string $category): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('category', $category)->get();
    }
}
