<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Database\Seeders;

use Core\Database\Seeders\Attributes\SeederAfter;
use Core\Database\Seeders\Attributes\SeederBefore;
use Core\Database\Seeders\Attributes\SeederPriority;
use Core\Database\Seeders\Exceptions\CircularDependencyException;
use ReflectionClass;

/**
 * Discovers and orders seeders from module directories.
 *
 * The SeederDiscovery service scans configured paths for seeder classes,
 * reads their priority and dependency declarations, and produces a
 * topologically sorted list of seeders ready for execution.
 *
 * ## Discovery
 *
 * Seeders are discovered by scanning for `*Seeder.php` files in
 * `Database/Seeders/` subdirectories of configured paths.
 *
 * ## Ordering
 *
 * Seeders can declare ordering preferences via:
 *
 * 1. **Priority** (property or attribute): Higher values run first
 *    ```php
 *    public int $priority = 10;
 *    // or
 *    #[SeederPriority(10)]
 *    ```
 *
 * 2. **After** (property or attribute): Must run after specified seeders
 *    ```php
 *    public array $after = [FeatureSeeder::class];
 *    // or
 *    #[SeederAfter(FeatureSeeder::class)]
 *    ```
 *
 * 3. **Before** (property or attribute): Must run before specified seeders
 *    ```php
 *    public array $before = [PackageSeeder::class];
 *    // or
 *    #[SeederBefore(PackageSeeder::class)]
 *    ```
 *
 * Dependencies take precedence over priority. Within the same dependency
 * level, seeders are sorted by priority (higher first).
 *
 *
 * @see SeederPriority For priority configuration
 * @see SeederAfter For dependency configuration
 * @see SeederBefore For reverse dependency configuration
 */
class SeederDiscovery
{
    /**
     * Default priority for seeders.
     */
    public const DEFAULT_PRIORITY = 50;

    /**
     * Discovered seeder metadata.
     *
     * @var array<string, array{priority: int, after: array<string>, before: array<string>}>
     */
    private array $seeders = [];

    /**
     * Paths to scan for seeders.
     *
     * @var array<string>
     */
    private array $paths = [];

    /**
     * Seeder classes to exclude.
     *
     * @var array<string>
     */
    private array $excluded = [];

    /**
     * Whether discovery has been performed.
     */
    private bool $discovered = false;

    /**
     * Create a new SeederDiscovery instance.
     *
     * @param  array<string>  $paths  Directories to scan for modules
     * @param  array<string>  $excluded  Seeder classes to exclude
     */
    public function __construct(array $paths = [], array $excluded = [])
    {
        $this->paths = $paths;
        $this->excluded = $excluded;
    }

    /**
     * Add paths to scan for seeders.
     *
     * @param  array<string>  $paths  Directories to add
     * @return $this
     */
    public function addPaths(array $paths): self
    {
        $this->paths = array_merge($this->paths, $paths);
        $this->discovered = false;

        return $this;
    }

    /**
     * Set paths to scan for seeders.
     *
     * @param  array<string>  $paths  Directories to scan
     * @return $this
     */
    public function setPaths(array $paths): self
    {
        $this->paths = $paths;
        $this->discovered = false;

        return $this;
    }

    /**
     * Add seeder classes to exclude.
     *
     * @param  array<string>  $classes  Seeder class names to exclude
     * @return $this
     */
    public function exclude(array $classes): self
    {
        $this->excluded = array_merge($this->excluded, $classes);

        return $this;
    }

    /**
     * Discover and return ordered seeder classes.
     *
     * @return array<string> Ordered list of seeder class names
     *
     * @throws CircularDependencyException If a circular dependency is detected
     */
    public function discover(): array
    {
        if (! $this->discovered) {
            $this->scanPaths();
            $this->discovered = true;
        }

        return $this->sort();
    }

    /**
     * Get all discovered seeders with their metadata.
     *
     * @return array<string, array{priority: int, after: array<string>, before: array<string>}>
     */
    public function getSeeders(): array
    {
        if (! $this->discovered) {
            $this->scanPaths();
            $this->discovered = true;
        }

        return $this->seeders;
    }

    /**
     * Reset the discovery cache.
     *
     * @return $this
     */
    public function reset(): self
    {
        $this->seeders = [];
        $this->discovered = false;

        return $this;
    }

    /**
     * Scan configured paths for seeder classes.
     */
    private function scanPaths(): void
    {
        $this->seeders = [];

        foreach ($this->paths as $path) {
            $this->scanPath($path);
        }
    }

    /**
     * Scan a single path for seeder classes.
     *
     * @param  string  $path  Directory to scan
     */
    private function scanPath(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        // Look for Database/Seeders directories in immediate subdirectories
        $pattern = "{$path}/*/Database/Seeders/*Seeder.php";
        $files = glob($pattern) ?: [];

        // Also check for seeders directly in the path (for Core modules)
        $directPattern = "{$path}/Database/Seeders/*Seeder.php";
        $directFiles = glob($directPattern) ?: [];
        $files = array_merge($files, $directFiles);

        foreach ($files as $file) {
            $class = $this->classFromFile($file);

            if ($class && class_exists($class) && ! in_array($class, $this->excluded, true)) {
                $this->seeders[$class] = $this->extractMetadata($class);
            }
        }
    }

    /**
     * Derive class name from file path.
     *
     * @param  string  $file  Path to the seeder file
     * @return string|null Fully qualified class name, or null if not determinable
     */
    private function classFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $contents, $nsMatch)) {
            $namespace = $nsMatch[1];
        } else {
            return null;
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $contents, $classMatch)) {
            $className = $classMatch[1];
        } else {
            return null;
        }

        return $namespace.'\\'.$className;
    }

    /**
     * Extract ordering metadata from a seeder class.
     *
     * @param  string  $class  Seeder class name
     * @return array{priority: int, after: array<string>, before: array<string>}
     */
    private function extractMetadata(string $class): array
    {
        $reflection = new ReflectionClass($class);

        return [
            'priority' => $this->extractPriority($reflection),
            'after' => $this->extractAfter($reflection),
            'before' => $this->extractBefore($reflection),
        ];
    }

    /**
     * Extract priority from a seeder class.
     *
     * Checks for SeederPriority attribute first, then falls back to
     * public $priority property.
     *
     * @param  ReflectionClass  $reflection  Reflection of the seeder class
     * @return int Priority value
     */
    private function extractPriority(ReflectionClass $reflection): int
    {
        // Check for attribute first
        $attributes = $reflection->getAttributes(SeederPriority::class);
        if (! empty($attributes)) {
            return $attributes[0]->newInstance()->priority;
        }

        // Fall back to property
        if ($reflection->hasProperty('priority')) {
            $prop = $reflection->getProperty('priority');
            if ($prop->isPublic() && ! $prop->isStatic()) {
                $defaultProps = $reflection->getDefaultProperties();
                if (isset($defaultProps['priority']) && is_int($defaultProps['priority'])) {
                    return $defaultProps['priority'];
                }
            }
        }

        return self::DEFAULT_PRIORITY;
    }

    /**
     * Extract 'after' dependencies from a seeder class.
     *
     * Checks for SeederAfter attributes first, then falls back to
     * public $after property.
     *
     * @param  ReflectionClass  $reflection  Reflection of the seeder class
     * @return array<string> Seeder classes that must run before this one
     */
    private function extractAfter(ReflectionClass $reflection): array
    {
        $after = [];

        // Check for attributes
        $attributes = $reflection->getAttributes(SeederAfter::class);
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $after = array_merge($after, $instance->seeders);
        }

        // If no attributes, check for property
        if (empty($after) && $reflection->hasProperty('after')) {
            $prop = $reflection->getProperty('after');
            if ($prop->isPublic() && ! $prop->isStatic()) {
                $defaultProps = $reflection->getDefaultProperties();
                if (isset($defaultProps['after']) && is_array($defaultProps['after'])) {
                    $after = $defaultProps['after'];
                }
            }
        }

        return $after;
    }

    /**
     * Extract 'before' dependencies from a seeder class.
     *
     * Checks for SeederBefore attributes first, then falls back to
     * public $before property.
     *
     * @param  ReflectionClass  $reflection  Reflection of the seeder class
     * @return array<string> Seeder classes that must run after this one
     */
    private function extractBefore(ReflectionClass $reflection): array
    {
        $before = [];

        // Check for attributes
        $attributes = $reflection->getAttributes(SeederBefore::class);
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $before = array_merge($before, $instance->seeders);
        }

        // If no attributes, check for property
        if (empty($before) && $reflection->hasProperty('before')) {
            $prop = $reflection->getProperty('before');
            if ($prop->isPublic() && ! $prop->isStatic()) {
                $defaultProps = $reflection->getDefaultProperties();
                if (isset($defaultProps['before']) && is_array($defaultProps['before'])) {
                    $before = $defaultProps['before'];
                }
            }
        }

        return $before;
    }

    /**
     * Topologically sort seeders based on dependencies and priority.
     *
     * Lower priority values run first (e.g., priority 10 runs before priority 50).
     *
     * @return array<string> Ordered seeder class names
     *
     * @throws CircularDependencyException If a circular dependency is detected
     */
    private function sort(): array
    {
        // Build adjacency list (seeder -> seeders that must run before it)
        $dependencies = [];
        foreach ($this->seeders as $seeder => $meta) {
            $dependencies[$seeder] = $meta['after'];

            // Process 'before' declarations (reverse dependencies)
            foreach ($meta['before'] as $dependent) {
                if (isset($this->seeders[$dependent])) {
                    $dependencies[$dependent][] = $seeder;
                }
            }
        }

        // Normalize dependencies to unique values
        foreach ($dependencies as $seeder => $deps) {
            $dependencies[$seeder] = array_unique($deps);
        }

        // Kahn's algorithm for topological sort
        $inDegree = [];
        $graph = [];

        // Initialize
        foreach ($dependencies as $seeder => $deps) {
            if (! isset($inDegree[$seeder])) {
                $inDegree[$seeder] = 0;
            }
            if (! isset($graph[$seeder])) {
                $graph[$seeder] = [];
            }

            foreach ($deps as $dep) {
                // Only count dependencies that exist in our discovered seeders
                if (isset($this->seeders[$dep])) {
                    $inDegree[$seeder]++;
                    $graph[$dep][] = $seeder;
                }
            }
        }

        // Start with seeders that have no dependencies
        $queue = [];
        foreach ($inDegree as $seeder => $degree) {
            if ($degree === 0) {
                $queue[] = $seeder;
            }
        }

        // Sort queue by priority (lower priority first - lower numbers run first)
        usort($queue, fn ($a, $b) => $this->seeders[$a]['priority'] <=> $this->seeders[$b]['priority']);

        $sorted = [];
        $processed = 0;

        while (! empty($queue)) {
            $seeder = array_shift($queue);
            $sorted[] = $seeder;
            $processed++;

            // Collect dependents that become ready
            $ready = [];
            foreach ($graph[$seeder] ?? [] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $ready[] = $dependent;
                }
            }

            // Sort newly ready seeders by priority and add to queue
            usort($ready, fn ($a, $b) => $this->seeders[$a]['priority'] <=> $this->seeders[$b]['priority']);
            $queue = array_merge($ready, $queue);

            // Re-sort the entire queue to maintain priority order
            usort($queue, fn ($a, $b) => $this->seeders[$a]['priority'] <=> $this->seeders[$b]['priority']);
        }

        // Check for cycles
        if ($processed < count($this->seeders)) {
            $this->detectCycle($dependencies);
        }

        return $sorted;
    }

    /**
     * Detect and report a cycle in the dependency graph.
     *
     * @param  array<string, array<string>>  $dependencies  Adjacency list
     *
     * @throws CircularDependencyException
     */
    private function detectCycle(array $dependencies): void
    {
        $visited = [];
        $recStack = [];
        $path = [];

        foreach (array_keys($this->seeders) as $seeder) {
            if ($this->dfsDetectCycle($seeder, $dependencies, $visited, $recStack, $path)) {
                return; // Exception already thrown
            }
        }

        // If we get here, there's a cycle but we couldn't find it
        throw new CircularDependencyException(['Unknown cycle detected']);
    }

    /**
     * DFS helper for cycle detection.
     *
     * @param  string  $seeder  Current seeder being visited
     * @param  array<string, array<string>>  $dependencies  Adjacency list
     * @param  array<string, bool>  $visited  Fully processed nodes
     * @param  array<string, bool>  $recStack  Nodes in current recursion stack
     * @param  array<string>  $path  Current path for error reporting
     *
     * @throws CircularDependencyException If a cycle is detected
     */
    private function dfsDetectCycle(
        string $seeder,
        array $dependencies,
        array &$visited,
        array &$recStack,
        array &$path
    ): bool {
        if (! isset($this->seeders[$seeder])) {
            return false;
        }

        if (isset($recStack[$seeder])) {
            throw CircularDependencyException::fromPath($path, $seeder);
        }

        if (isset($visited[$seeder])) {
            return false;
        }

        $visited[$seeder] = true;
        $recStack[$seeder] = true;
        $path[] = $seeder;

        foreach ($dependencies[$seeder] ?? [] as $dep) {
            if ($this->dfsDetectCycle($dep, $dependencies, $visited, $recStack, $path)) {
                return true;
            }
        }

        array_pop($path);
        unset($recStack[$seeder]);

        return false;
    }
}
