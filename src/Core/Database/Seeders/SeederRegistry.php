<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Database\Seeders;

use Core\Database\Seeders\Exceptions\CircularDependencyException;

/**
 * Manual seeder registration for explicit control over seeder ordering.
 *
 * Use SeederRegistry when you want explicit control over which seeders run
 * and in what order, rather than relying on auto-discovery.
 *
 * ## Example
 *
 * ```php
 * $registry = new SeederRegistry();
 *
 * $registry
 *     ->register(FeatureSeeder::class, priority: 10)
 *     ->register(PackageSeeder::class, after: [FeatureSeeder::class])
 *     ->register(WorkspaceSeeder::class, after: [PackageSeeder::class]);
 *
 * // Get ordered seeders
 * $seeders = $registry->getOrdered();
 * ```
 *
 *
 * @see SeederDiscovery For auto-discovered seeders
 */
class SeederRegistry
{
    /**
     * Registered seeder metadata.
     *
     * @var array<string, array{priority: int, after: array<string>, before: array<string>}>
     */
    private array $seeders = [];

    /**
     * Register a seeder class.
     *
     * @param  string  $class  Fully qualified seeder class name
     * @param  int  $priority  Priority (higher runs first, default 50)
     * @param  array<string>  $after  Seeders that must run before this one
     * @param  array<string>  $before  Seeders that must run after this one
     * @return $this
     */
    public function register(
        string $class,
        int $priority = SeederDiscovery::DEFAULT_PRIORITY,
        array $after = [],
        array $before = []
    ): self {
        $this->seeders[$class] = [
            'priority' => $priority,
            'after' => $after,
            'before' => $before,
        ];

        return $this;
    }

    /**
     * Register multiple seeders at once.
     *
     * @param  array<string, array{priority?: int, after?: array<string>, before?: array<string>}|int>  $seeders
     *                                                                                                            Either [Class => priority] or [Class => ['priority' => n, 'after' => [], 'before' => []]]
     * @return $this
     */
    public function registerMany(array $seeders): self
    {
        foreach ($seeders as $class => $config) {
            if (is_int($config)) {
                $this->register($class, priority: $config);
            } else {
                $this->register(
                    $class,
                    priority: $config['priority'] ?? SeederDiscovery::DEFAULT_PRIORITY,
                    after: $config['after'] ?? [],
                    before: $config['before'] ?? []
                );
            }
        }

        return $this;
    }

    /**
     * Remove a seeder from the registry.
     *
     * @param  string  $class  Seeder class to remove
     * @return $this
     */
    public function remove(string $class): self
    {
        unset($this->seeders[$class]);

        return $this;
    }

    /**
     * Check if a seeder is registered.
     *
     * @param  string  $class  Seeder class to check
     */
    public function has(string $class): bool
    {
        return isset($this->seeders[$class]);
    }

    /**
     * Get all registered seeders.
     *
     * @return array<string, array{priority: int, after: array<string>, before: array<string>}>
     */
    public function all(): array
    {
        return $this->seeders;
    }

    /**
     * Get ordered seeder classes.
     *
     * @return array<string> Ordered list of seeder class names
     *
     * @throws CircularDependencyException If a circular dependency is detected
     */
    public function getOrdered(): array
    {
        // Use SeederDiscovery's sorting logic by creating a temporary instance
        $discovery = new class extends SeederDiscovery
        {
            /**
             * @param  array<string, array{priority: int, after: array<string>, before: array<string>}>  $seeders
             */
            public function setSeeders(array $seeders): void
            {
                $reflection = new \ReflectionClass(SeederDiscovery::class);
                $prop = $reflection->getProperty('seeders');
                $prop->setValue($this, $seeders);

                $discovered = $reflection->getProperty('discovered');
                $discovered->setValue($this, true);
            }
        };

        $discovery->setSeeders($this->seeders);

        return $discovery->discover();
    }

    /**
     * Merge another registry into this one.
     *
     * @param  SeederRegistry  $registry  Registry to merge
     * @return $this
     */
    public function merge(SeederRegistry $registry): self
    {
        foreach ($registry->all() as $class => $meta) {
            if (! isset($this->seeders[$class])) {
                $this->seeders[$class] = $meta;
            }
        }

        return $this;
    }

    /**
     * Clear all registered seeders.
     *
     * @return $this
     */
    public function clear(): self
    {
        $this->seeders = [];

        return $this;
    }
}
