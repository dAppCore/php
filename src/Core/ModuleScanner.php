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
 * The class name is read out of the file's own `namespace` declaration, so a
 * package may lay itself out however it likes and still be found. Only a
 * Boot.php that declares no namespace falls back to the directory convention
 * (`/Core` → `Core\`, `/Mod` → `Mod\`, `/Website` → `Website\`, `/Plug` → `Plug\`).
 *
 * {@see classFromFile} says why the convention stopped being the primary rule.
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

            foreach (glob($path . '/*/Boot.php') as $file) {
                $class = $this->classFromFile($file, $path);
                if (! $class) {
                    continue;
                }
                if (! class_exists($class)) {
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
     * Determine the fully qualified class name a Boot.php file declares.
     *
     * Read out of the file, not guessed from its path. The file says which
     * namespace it is in; nothing else has to agree with it.
     *
     * It used to be derived from the path — `/Core` meant `Core\`, `/Mod` meant
     * `Mod\` — and that is a convention rather than a fact. It held for a
     * consuming application laid out the way the scaffolding lays one out, and
     * broke everywhere else, including in this framework: `src/Mod/Trees/Boot.php`
     * declares `Core\Mod\Trees` and the path rule produced `Mod\Trees\Boot`.
     *
     * That failure was not a miss. In an application that happens to own a
     * module of the same name — host.uk.com has an `app/Mod/Trees` — the wrong
     * name *resolves*, and the scanner wires the consumer's unrelated class in
     * place of this one, silently, for a Boot file it read from vendor. A rule
     * that can attribute one package's file to another package's class is not a
     * rule worth keeping.
     *
     * The path convention survives only as a fallback for a Boot.php with no
     * namespace declaration at all.
     *
     * @param  string  $file  Absolute path to the Boot.php file
     * @param  string  $basePath  Base directory path (e.g., app_path('Mod'))
     * @return string|null Fully qualified class name, or null if it cannot be determined
     */
    private function classFromFile(string $file, string $basePath): ?string
    {
        $declared = $this->namespaceFromSource($file);

        if ($declared !== null) {
            return $declared.'\\'.basename($file, '.php');
        }

        return $this->classFromPath($file, $basePath);
    }

    /**
     * Read the namespace a file declares, without loading it.
     *
     * Only the head of the file is read: a namespace declaration is required to
     * be the first statement, so 8KB is more than enough, and this runs for
     * every Boot.php on every request.
     *
     * @return string|null the declared namespace, or null for the global one
     */
    private function namespaceFromSource(string $file): ?string
    {
        $head = @file_get_contents($file, false, null, 0, 8192);

        if ($head === false) {
            return null;
        }

        return preg_match('/^\s*namespace\s+([A-Za-z0-9_\x80-\xff\\\\]+)\s*;/m', $head, $matches) === 1
            ? $matches[1]
            : null;
    }

    /**
     * The old path-derived name, kept for a Boot.php that declares no namespace.
     *
     * Maps file paths to namespaces by directory convention:
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
    private function classFromPath(string $file, string $basePath): ?string
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
            return 'Core\\' . $namespace;
        }

        if (str_contains($basePath, '/Mod')) {
            return 'Mod\\' . $namespace;
        }

        if (str_contains($basePath, '/Website')) {
            return 'Website\\' . $namespace;
        }

        if (str_contains($basePath, '/Plug')) {
            return 'Plug\\' . $namespace;
        }

        // Fallback - try to determine from directory name
        $dirName = basename($basePath);

        return sprintf('%s\%s', $dirName, $namespace);
    }
}
