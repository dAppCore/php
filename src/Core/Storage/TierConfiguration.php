<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Storage;

/**
 * Configuration for a single cache tier.
 *
 * Represents the configuration for one tier in a multi-tier cache system.
 * Each tier has a name, driver, TTL, and optional promotion settings.
 *
 * ## Tier Types
 *
 * Tiers are ordered from fastest to slowest:
 * - **memory**: In-process array cache (fastest, request-scoped)
 * - **redis**: Shared memory cache (fast, distributed)
 * - **file**: File-based cache (medium speed, persistent)
 * - **database**: Database-backed cache (slowest, most durable)
 *
 * ## Usage
 *
 * ```php
 * $tier = new TierConfiguration(
 *     name: 'memory',
 *     driver: 'array',
 *     ttl: 60,
 *     promoteOnHit: true,
 * );
 *
 * // Or use factory methods
 * $memory = TierConfiguration::memory(ttl: 60);
 * $redis = TierConfiguration::redis(ttl: 3600);
 * $database = TierConfiguration::database(ttl: 86400);
 * ```
 */
class TierConfiguration
{
    /**
     * Default TTL values per tier type (in seconds).
     */
    public const DEFAULT_TTL = [
        'memory' => 60,        // 1 minute
        'redis' => 3600,       // 1 hour
        'file' => 7200,        // 2 hours
        'database' => 86400,   // 24 hours
    ];

    /**
     * Create a new tier configuration.
     *
     * @param  string  $name  Tier name for identification
     * @param  string  $driver  Laravel cache driver name
     * @param  int  $ttl  Time-to-live in seconds
     * @param  bool  $promoteOnHit  Whether to promote values up to this tier on read
     * @param  bool  $enabled  Whether this tier is enabled
     * @param  int  $priority  Lower value = checked first
     */
    public function __construct(
        public readonly string $name,
        public readonly string $driver,
        public readonly int $ttl = 3600,
        public readonly bool $promoteOnHit = true,
        public readonly bool $enabled = true,
        public readonly int $priority = 50,
    ) {}

    /**
     * Create a memory (array) tier configuration.
     *
     * Memory tier is the fastest but request-scoped. Values are lost
     * when the request ends. Best for very hot data accessed multiple
     * times per request.
     */
    public static function memory(
        int $ttl = 60,
        bool $promoteOnHit = true,
        int $priority = 10,
    ): self {
        return new self(
            name: 'memory',
            driver: 'array',
            ttl: $ttl,
            promoteOnHit: $promoteOnHit,
            priority: $priority,
        );
    }

    /**
     * Create a Redis tier configuration.
     *
     * Redis tier is fast and distributed across processes/servers.
     * Use the resilient-redis driver for automatic fallback.
     */
    public static function redis(
        int $ttl = 3600,
        bool $promoteOnHit = true,
        int $priority = 20,
        string $driver = 'redis',
    ): self {
        return new self(
            name: 'redis',
            driver: $driver,
            ttl: $ttl,
            promoteOnHit: $promoteOnHit,
            priority: $priority,
        );
    }

    /**
     * Create a file tier configuration.
     *
     * File tier is slower than Redis but requires no external services.
     * Good as a middle tier when Redis isn't available.
     */
    public static function file(
        int $ttl = 7200,
        bool $promoteOnHit = true,
        int $priority = 30,
    ): self {
        return new self(
            name: 'file',
            driver: 'file',
            ttl: $ttl,
            promoteOnHit: $promoteOnHit,
            priority: $priority,
        );
    }

    /**
     * Create a database tier configuration.
     *
     * Database tier is the slowest but most durable. Values persist
     * across deployments and server restarts. Best as the final fallback.
     */
    public static function database(
        int $ttl = 86400,
        bool $promoteOnHit = false,
        int $priority = 40,
    ): self {
        return new self(
            name: 'database',
            driver: 'database',
            ttl: $ttl,
            promoteOnHit: $promoteOnHit,
            priority: $priority,
        );
    }

    /**
     * Create a configuration from an array.
     *
     * @param  array{name: string, driver: string, ttl?: int, promoteOnHit?: bool, enabled?: bool, priority?: int}  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            name: $config['name'],
            driver: $config['driver'],
            ttl: $config['ttl'] ?? self::DEFAULT_TTL[$config['name']] ?? 3600,
            promoteOnHit: $config['promoteOnHit'] ?? $config['promote_on_hit'] ?? true,
            enabled: $config['enabled'] ?? true,
            priority: $config['priority'] ?? 50,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array{name: string, driver: string, ttl: int, promoteOnHit: bool, enabled: bool, priority: int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'driver' => $this->driver,
            'ttl' => $this->ttl,
            'promoteOnHit' => $this->promoteOnHit,
            'enabled' => $this->enabled,
            'priority' => $this->priority,
        ];
    }

    /**
     * Create a modified copy with different TTL.
     */
    public function withTtl(int $ttl): self
    {
        return new self(
            name: $this->name,
            driver: $this->driver,
            ttl: $ttl,
            promoteOnHit: $this->promoteOnHit,
            enabled: $this->enabled,
            priority: $this->priority,
        );
    }

    /**
     * Create a modified copy with promotion enabled/disabled.
     */
    public function withPromotion(bool $promoteOnHit): self
    {
        return new self(
            name: $this->name,
            driver: $this->driver,
            ttl: $this->ttl,
            promoteOnHit: $promoteOnHit,
            enabled: $this->enabled,
            priority: $this->priority,
        );
    }

    /**
     * Create a modified copy with enabled/disabled state.
     */
    public function withEnabled(bool $enabled): self
    {
        return new self(
            name: $this->name,
            driver: $this->driver,
            ttl: $this->ttl,
            promoteOnHit: $this->promoteOnHit,
            enabled: $enabled,
            priority: $this->priority,
        );
    }
}
