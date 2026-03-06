<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config;

use Core\Config\Enums\ConfigType;
use Core\Config\Enums\ScopeType;

/**
 * Immutable result of config resolution.
 *
 * Contains the resolved value plus metadata about where it came from.
 * This is what gets cached for fast last-mile access.
 */
final readonly class ConfigResult
{
    public function __construct(
        public string $key,
        public mixed $value,
        public ConfigType $type,
        public bool $found,
        public bool $locked,
        public bool $virtual = false,
        public ?ScopeType $resolvedFrom = null,
        public ?int $profileId = null,
        public ?int $channelId = null,
    ) {}

    /**
     * Create a found result.
     */
    public static function found(
        string $key,
        mixed $value,
        ConfigType $type,
        bool $locked,
        ?ScopeType $resolvedFrom = null,
        ?int $profileId = null,
        ?int $channelId = null,
    ): self {
        return new self(
            key: $key,
            value: $type->cast($value),
            type: $type,
            found: true,
            locked: $locked,
            virtual: false,
            resolvedFrom: $resolvedFrom,
            profileId: $profileId,
            channelId: $channelId,
        );
    }

    /**
     * Create a result from a virtual provider.
     */
    public static function virtual(
        string $key,
        mixed $value,
        ConfigType $type,
    ): self {
        return new self(
            key: $key,
            value: $type->cast($value),
            type: $type,
            found: true,
            locked: false,
            virtual: true,
        );
    }

    /**
     * Create a not-found result with default value.
     */
    public static function notFound(string $key, mixed $defaultValue, ConfigType $type): self
    {
        return new self(
            key: $key,
            value: $type->cast($defaultValue),
            type: $type,
            found: false,
            locked: false,
        );
    }

    /**
     * Create a result for unconfigured key.
     */
    public static function unconfigured(string $key): self
    {
        return new self(
            key: $key,
            value: null,
            type: ConfigType::STRING,
            found: false,
            locked: false,
        );
    }

    /**
     * Check if the key was found (has a value).
     */
    public function isConfigured(): bool
    {
        return $this->found && $this->value !== null;
    }

    /**
     * Check if the value is locked (FINAL).
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * Check if the value came from a virtual provider.
     */
    public function isVirtual(): bool
    {
        return $this->virtual;
    }

    /**
     * Get the value, with optional fallback.
     */
    public function get(mixed $default = null): mixed
    {
        return $this->value ?? $default;
    }

    /**
     * Get value as string.
     */
    public function string(string $default = ''): string
    {
        return (string) ($this->value ?? $default);
    }

    /**
     * Get value as integer.
     */
    public function int(int $default = 0): int
    {
        return (int) ($this->value ?? $default);
    }

    /**
     * Get value as boolean.
     */
    public function bool(bool $default = false): bool
    {
        return (bool) ($this->value ?? $default);
    }

    /**
     * Get value as array.
     */
    public function array(array $default = []): array
    {
        if ($this->value === null) {
            return $default;
        }

        return is_array($this->value) ? $this->value : $default;
    }

    /**
     * Convert to array for caching.
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type->value,
            'found' => $this->found,
            'locked' => $this->locked,
            'virtual' => $this->virtual,
            'resolved_from' => $this->resolvedFrom?->value,
            'profile_id' => $this->profileId,
            'channel_id' => $this->channelId,
        ];
    }

    /**
     * Reconstruct from cached array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            value: $data['value'],
            type: ConfigType::from($data['type']),
            found: $data['found'],
            locked: $data['locked'],
            virtual: $data['virtual'] ?? false,
            resolvedFrom: ($data['resolved_from'] ?? null) ? ScopeType::from($data['resolved_from']) : null,
            profileId: $data['profile_id'] ?? null,
            channelId: $data['channel_id'] ?? null,
        );
    }
}
