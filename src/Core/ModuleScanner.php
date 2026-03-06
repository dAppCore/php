<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core;

use ReflectionClass;

/**
 * Scans module Boot.php files for event listener declarations.
 *
 * The ModuleScanner is responsible for discovering modules that wish to participate
 * in the lifecycle event system. It reads the static `$listens` property from Boot
 * classes without instantiating them, enabling lazy loading of modules.
 *
 * ## How It Works
 *
 * The scanner looks for `Boot.php` files in immediate subdirectories of the given paths.
 * Each Boot class can declare a `$listens` array mapping events to handler methods:
 *
 * ```php
 * class Boot
 * {
 *     public static array $listens = [
 *         WebRoutesRegistering::class => 'onWebRoutes',
 *         AdminPanelBooting::class => ['onAdmin', 10],  // With priority
 *     ];
 * }
 * ```
 *
 * ## Priority System
 *
 * Listeners can optionally specify a priority (default: 0). Higher priority values
 * run first. Use array syntax to specify priority:
 *
 * - `'methodName'` - Default priority 0
 * - `['methodName', 10]` - Priority 10 (runs before priority 0)
 * - `['methodName', -5]` - Priority -5 (runs after priority 0)
 *
 * ## Namespace Detection
 *
 * The scanner automatically determines namespaces based on path:
 * - `/Core` paths map to `Core\` namespace
 * - `/Mod` paths map to `Mod\` namespace
 * - `/Website` paths map to `Website\` namespace
 * - `/Plug` paths map to `Plug\` namespace
 *
 * ## Usage Example
 *
 * ```php
 * $scanner = new ModuleScanner();
 * $mappings = $scanner->scan([app_path('Core'), app_path('Mod')]);
 * // Returns: [EventClass => [ModuleClass => ['method' => 'name', 'priority' => 0]]]
 * ```
 *
 *
 * @see ModuleRegistry For registering discovered listeners with Laravel's event system
 * @see LazyModuleListener For the lazy-loading listener wrapper
 */
class ModuleScanner
{
    /**
     * Default priority for listeners without explicit priority.
     */
    public const DEFAULT_PRIORITY = 0;

    /**
     * Scan directories for Boot.php files with $listens declarations.
     *
     * @param  array<string>  $paths  Directories to scan
     * @return array<string, array<string, array{method: string, priority: int}>> Event => [Module => config] mappings
     */
    public function scan(array $paths): array
    {
        $mappings = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach (glob("{$path}/*/Boot.php") as $file) {
                $class = $this->classFromFile($file, $path);

                if (! $class || ! class_exists($class)) {
                    continue;
                }

                $listens = $this->extractListens($class);

                foreach ($listens as $event => $config) {
                    $mappings[$event][$class] = $config;
                }
            }
        }

        return $mappings;
    }

    /**
     * Extract the $listens array from a class without instantiation.
     *
     * Supports two formats:
     *   - Simple: EventClass::class => 'methodName'
     *   - With priority: EventClass::class => ['methodName', priority]
     *
     * @return array<string, array{method: string, priority: int}> Event => config mappings
     */
    public function extractListens(string $class): array
    {
        try {
            $reflection = new ReflectionClass($class);

            if (! $reflection->hasProperty('listens')) {
                return [];
            }

            $prop = $reflection->getProperty('listens');

            if (! $prop->isStatic() || ! $prop->isPublic()) {
                return [];
            }

            $listens = $prop->getValue();

            if (! is_array($listens)) {
                return [];
            }

            return $this->normalizeListens($listens);
        } catch (\ReflectionException) {
            return [];
        }
    }

    /**
     * Normalize listener declarations to consistent format.
     *
     * @param  array<string, string|array{0: string, 1?: int}>  $listens  Raw listener declarations
     * @return array<string, array{method: string, priority: int}> Normalized declarations
     */
    private function normalizeListens(array $listens): array
    {
        $normalized = [];

        foreach ($listens as $event => $value) {
            if (is_string($value)) {
                $normalized[$event] = [
                    'method' => $value,
                    'priority' => self::DEFAULT_PRIORITY,
                ];
            } elseif (is_array($value) && isset($value[0])) {
                $normalized[$event] = [
                    'method' => $value[0],
                    'priority' => (int) ($value[1] ?? self::DEFAULT_PRIORITY),
                ];
            }
        }

        return $normalized;
    }

    /**
     * Derive fully qualified class name from file path.
     *
     * Maps file paths to PSR-4 namespaces based on directory structure:
     *
     * - `app/Mod/Commerce/Boot.php` becomes `Mod\Commerce\Boot`
     * - `app/Core/Cdn/Boot.php` becomes `Core\Cdn\Boot`
     * - `app/Website/Acme/Boot.php` becomes `Website\Acme\Boot`
     * - `app/Plug/Analytics/Boot.php` becomes `Plug\Analytics\Boot`
     *
     * @param  string  $file  Absolute path to the Boot.php file
     * @param  string  $basePath  Base directory path (e.g., app_path('Mod'))
     * @return string|null Fully qualified class name, or null if path doesn't match expected structure
     */
    private function classFromFile(string $file, string $basePath): ?string
    {
        // Normalise paths
        $file = str_replace('\\', '/', realpath($file) ?: $file);
        $basePath = str_replace('\\', '/', realpath($basePath) ?: $basePath);

        // Get relative path from base
        if (! str_starts_with($file, $basePath)) {
            return null;
        }

        $relative = substr($file, strlen($basePath) + 1);

        // Remove .php extension
        $relative = preg_replace('/\.php$/', '', $relative);

        // Convert path separators to namespace separators
        $namespace = str_replace('/', '\\', $relative);

        // Determine root namespace based on path
        if (str_contains($basePath, '/Core')) {
            return "Core\\{$namespace}";
        }

        if (str_contains($basePath, '/Mod')) {
            return "Mod\\{$namespace}";
        }

        if (str_contains($basePath, '/Website')) {
            return "Website\\{$namespace}";
        }

        if (str_contains($basePath, '/Plug')) {
            return "Plug\\{$namespace}";
        }

        // Fallback - try to determine from directory name
        $dirName = basename($basePath);

        return "{$dirName}\\{$namespace}";
    }
}
