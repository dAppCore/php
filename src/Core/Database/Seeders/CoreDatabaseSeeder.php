<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Database\Seeders;

use Illuminate\Console\Command;
use Illuminate\Database\Seeder;

/**
 * Base database seeder with auto-discovery support.
 *
 * This class automatically discovers and runs all module seeders in the
 * correct order based on their priority and dependency declarations.
 *
 * ## Usage
 *
 * Apps can extend this class directly:
 *
 * ```php
 * class DatabaseSeeder extends CoreDatabaseSeeder
 * {
 *     // Optionally override paths or excluded seeders
 *     protected function getSeederPaths(): array
 *     {
 *         return [
 *             app_path('Core'),
 *             app_path('Mod'),
 *             base_path('packages/my-package/src'),
 *         ];
 *     }
 * }
 * ```
 *
 * Or use the class directly:
 *
 * ```bash
 * php artisan db:seed --class=Core\\Database\\Seeders\\CoreDatabaseSeeder
 * ```
 *
 * ## Filtering
 *
 * Seeders can be filtered using Artisan command options:
 *
 * - `--exclude=SeederName` - Skip specific seeders
 * - `--only=SeederName` - Run only specific seeders
 *
 * Multiple filters can be specified by repeating the option.
 *
 *
 * @see SeederDiscovery For the discovery mechanism
 * @see SeederRegistry For manual seeder registration
 */
class CoreDatabaseSeeder extends Seeder
{
    /**
     * The seeder discovery instance.
     */
    protected ?SeederDiscovery $discovery = null;

    /**
     * The seeder registry for manual registrations.
     */
    protected ?SeederRegistry $registry = null;

    /**
     * Whether to use auto-discovery.
     */
    protected bool $autoDiscover = true;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seeders = $this->getSeedersToRun();

        if (empty($seeders)) {
            $this->info('No seeders found to run.');

            return;
        }

        $this->info(sprintf('Running %d seeders...', count($seeders)));
        $this->newLine();

        foreach ($seeders as $seeder) {
            $shortName = $this->getShortName($seeder);
            $this->info("Running: {$shortName}");

            $this->call($seeder);
        }

        $this->newLine();
        $this->info('Database seeding completed successfully.');
    }

    /**
     * Get the list of seeders to run.
     *
     * @return array<string> Ordered list of seeder class names
     */
    protected function getSeedersToRun(): array
    {
        $seeders = $this->discoverSeeders();

        // Apply filters
        $seeders = $this->applyExcludeFilter($seeders);
        $seeders = $this->applyOnlyFilter($seeders);

        return $seeders;
    }

    /**
     * Discover all seeders.
     *
     * @return array<string> Ordered list of seeder class names
     */
    protected function discoverSeeders(): array
    {
        // Check if auto-discovery is enabled
        if (! $this->shouldAutoDiscover()) {
            return $this->getManualSeeders();
        }

        $discovery = $this->getDiscovery();

        return $discovery->discover();
    }

    /**
     * Get manually registered seeders.
     *
     * @return array<string>
     */
    protected function getManualSeeders(): array
    {
        $registry = $this->getRegistry();

        return $registry->getOrdered();
    }

    /**
     * Get the seeder discovery instance.
     */
    protected function getDiscovery(): SeederDiscovery
    {
        if ($this->discovery === null) {
            $this->discovery = new SeederDiscovery(
                $this->getSeederPaths(),
                $this->getExcludedSeeders()
            );
        }

        return $this->discovery;
    }

    /**
     * Get the seeder registry instance.
     */
    protected function getRegistry(): SeederRegistry
    {
        if ($this->registry === null) {
            $this->registry = new SeederRegistry;
            $this->registerSeeders($this->registry);
        }

        return $this->registry;
    }

    /**
     * Register seeders manually when auto-discovery is disabled.
     *
     * Override this method in subclasses to add seeders.
     *
     * @param  SeederRegistry  $registry  The registry to add seeders to
     */
    protected function registerSeeders(SeederRegistry $registry): void
    {
        // Override in subclasses
    }

    /**
     * Get paths to scan for seeders.
     *
     * Override this method to customize seeder paths.
     *
     * @return array<string>
     */
    protected function getSeederPaths(): array
    {
        // Use config if available, otherwise use defaults
        $config = config('core.seeders.paths');

        if (is_array($config) && ! empty($config)) {
            return $config;
        }

        return [
            app_path('Core'),
            app_path('Mod'),
            app_path('Website'),
        ];
    }

    /**
     * Get seeders to exclude.
     *
     * Override this method to customize excluded seeders.
     *
     * @return array<string>
     */
    protected function getExcludedSeeders(): array
    {
        return config('core.seeders.exclude', []);
    }

    /**
     * Check if auto-discovery should be used.
     */
    protected function shouldAutoDiscover(): bool
    {
        if (! $this->autoDiscover) {
            return false;
        }

        return config('core.seeders.auto_discover', true);
    }

    /**
     * Apply the --exclude filter.
     *
     * @param  array<string>  $seeders  List of seeder classes
     * @return array<string> Filtered list
     */
    protected function applyExcludeFilter(array $seeders): array
    {
        $excludes = $this->getCommandOption('exclude');

        if (empty($excludes)) {
            return $seeders;
        }

        $excludePatterns = is_array($excludes) ? $excludes : [$excludes];

        return array_filter($seeders, function ($seeder) use ($excludePatterns) {
            foreach ($excludePatterns as $pattern) {
                if ($this->matchesPattern($seeder, $pattern)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Apply the --only filter.
     *
     * @param  array<string>  $seeders  List of seeder classes
     * @return array<string> Filtered list
     */
    protected function applyOnlyFilter(array $seeders): array
    {
        $only = $this->getCommandOption('only');

        if (empty($only)) {
            return $seeders;
        }

        $onlyPatterns = is_array($only) ? $only : [$only];

        return array_values(array_filter($seeders, function ($seeder) use ($onlyPatterns) {
            foreach ($onlyPatterns as $pattern) {
                if ($this->matchesPattern($seeder, $pattern)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * Check if a seeder matches a pattern.
     *
     * Patterns can be:
     * - Full class name: Core\Tenant\Database\Seeders\FeatureSeeder
     * - Short name: FeatureSeeder
     * - Partial match: Feature (matches FeatureSeeder)
     *
     * @param  string  $seeder  Full class name
     * @param  string  $pattern  Pattern to match
     */
    protected function matchesPattern(string $seeder, string $pattern): bool
    {
        // Exact match
        if ($seeder === $pattern) {
            return true;
        }

        // Short name match
        $shortName = $this->getShortName($seeder);
        if ($shortName === $pattern) {
            return true;
        }

        // Partial match (contains)
        if (str_contains($shortName, $pattern) || str_contains($seeder, $pattern)) {
            return true;
        }

        return false;
    }

    /**
     * Get a command option value.
     *
     * @param  string  $name  Option name
     */
    protected function getCommandOption(string $name): mixed
    {
        if (! $this->command instanceof Command) {
            return null;
        }

        // Check if the option exists before getting it
        if (! $this->command->hasOption($name)) {
            return null;
        }

        return $this->command->option($name);
    }

    /**
     * Get the short (class only) name of a seeder.
     *
     * @param  string  $class  Fully qualified class name
     * @return string Class name without namespace
     */
    protected function getShortName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    /**
     * Output an info message.
     *
     * @param  string  $message  Message to output
     */
    protected function info(string $message): void
    {
        if ($this->command instanceof Command) {
            $this->command->info($message);
        }
    }

    /**
     * Output a newline.
     */
    protected function newLine(): void
    {
        if ($this->command instanceof Command) {
            $this->command->newLine();
        }
    }
}
