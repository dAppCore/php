<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Lang\Coverage;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Translation Coverage Reporting Service.
 *
 * Scans code for translation key usage and compares against translation files
 * to identify missing and unused translation keys.
 *
 * Features:
 * - Scans PHP, Blade, and JS/Vue files for translation function calls
 * - Supports __(), trans(), trans_choice(), @lang, and JS equivalents
 * - Compares found keys against translation files
 * - Reports missing keys (used in code but not defined)
 * - Reports unused keys (defined but not used in code)
 * - Supports multiple locales and namespaces
 * - Generates coverage statistics
 *
 * Usage:
 *   $coverage = app(TranslationCoverage::class);
 *
 *   // Scan default paths
 *   $report = $coverage->analyze();
 *
 *   // Scan specific paths
 *   $report = $coverage->analyze([
 *       'code_paths' => [app_path(), resource_path('views')],
 *       'lang_path' => lang_path(),
 *   ]);
 *
 * Configuration in config/core.php:
 *   'lang' => [
 *       'coverage' => [
 *           'enabled' => true,
 *           'code_paths' => null,   // null = auto-detect
 *           'exclude_paths' => ['vendor', 'node_modules'],
 *           'exclude_patterns' => [],
 *       ],
 *   ]
 */
class TranslationCoverage
{
    /**
     * Regular expressions to find translation function calls.
     * Captures the translation key from various formats.
     *
     * @var array<string, string>
     */
    protected const PATTERNS = [
        // PHP: __('key'), trans('key'), trans_choice('key', n)
        'php_double' => '/__\s*\(\s*["\']([^"\']+)["\']/u',
        'trans_double' => '/\btrans\s*\(\s*["\']([^"\']+)["\']/u',
        'trans_choice' => '/trans_choice\s*\(\s*["\']([^"\']+)["\']/u',
        'lang_get' => '/Lang::get\s*\(\s*["\']([^"\']+)["\']/u',
        'lang_has' => '/Lang::has\s*\(\s*["\']([^"\']+)["\']/u',
        'lang_choice' => '/Lang::choice\s*\(\s*["\']([^"\']+)["\']/u',

        // Blade: @lang('key'), {{ __('key') }}
        'blade_lang' => '/@lang\s*\(\s*["\']([^"\']+)["\']\s*\)/u',

        // JavaScript/Vue: $t('key'), this.$t('key'), t('key')
        'js_t' => '/\$t\s*\(\s*["\']([^"\']+)["\']/u',
        'js_t_plain' => '/\bt\s*\(\s*["\']([^"\']+)["\']/u',
    ];

    /**
     * File extensions to scan.
     *
     * @var array<string>
     */
    protected const EXTENSIONS = ['php', 'blade.php', 'js', 'vue', 'ts', 'tsx'];

    /**
     * Paths to exclude from scanning.
     *
     * @var array<string>
     */
    protected array $excludePaths;

    /**
     * Key patterns to exclude from reporting.
     *
     * @var array<string>
     */
    protected array $excludePatterns;

    public function __construct()
    {
        $this->excludePaths = config('core.lang.coverage.exclude_paths', [
            'vendor',
            'node_modules',
            'storage',
            '.git',
        ]);

        $this->excludePatterns = config('core.lang.coverage.exclude_patterns', []);
    }

    /**
     * Analyze translation coverage.
     *
     * @param  array{code_paths?: array, lang_path?: string, locales?: array, namespace?: string|null}  $options
     */
    public function analyze(array $options = []): TranslationCoverageReport
    {
        $codePaths = $options['code_paths'] ?? $this->getDefaultCodePaths();
        $langPath = $options['lang_path'] ?? lang_path();
        $locales = $options['locales'] ?? $this->detectLocales($langPath);
        $namespace = $options['namespace'] ?? null;

        // 1. Scan code for used translation keys
        $usedKeys = $this->scanCodeForKeys($codePaths);

        // 2. Load defined keys from translation files
        $definedKeys = $this->loadDefinedKeys($langPath, $locales, $namespace);

        // 3. Compare and generate report
        return $this->generateReport($usedKeys, $definedKeys, $locales);
    }

    /**
     * Scan code paths for translation key usage.
     *
     * @param  array<string>  $paths
     * @return Collection<string, array{file: string, line: int, context: string}>
     */
    public function scanCodeForKeys(array $paths): Collection
    {
        $keys = collect();

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $files = $this->getFilesToScan($path);

            foreach ($files as $file) {
                $fileKeys = $this->scanFile($file);
                $keys = $keys->merge($fileKeys);
            }
        }

        return $keys;
    }

    /**
     * Get files to scan from a directory.
     *
     * @return array<string>
     */
    protected function getFilesToScan(string $path): array
    {
        $files = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                $filePath = $file->getPathname();

                // Skip excluded paths
                if ($this->shouldExcludePath($filePath)) {
                    continue;
                }

                // Check extension
                if ($this->hasValidExtension($filePath)) {
                    $files[] = $filePath;
                }
            }
        } catch (\Exception $e) {
            // Directory not readable, skip
        }

        return $files;
    }

    /**
     * Scan a single file for translation keys.
     *
     * @return Collection<string, array{file: string, line: int, context: string}>
     */
    protected function scanFile(string $filePath): Collection
    {
        $keys = collect();

        try {
            $content = File::get($filePath);
            $lines = explode("\n", $content);

            foreach (self::PATTERNS as $name => $pattern) {
                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[1] as $match) {
                        $key = $match[0];
                        $offset = $match[1];

                        // Skip dynamic keys (containing variables)
                        if (str_contains($key, '$') || str_contains($key, '{')) {
                            continue;
                        }

                        // Skip if matches exclude pattern
                        if ($this->shouldExcludeKey($key)) {
                            continue;
                        }

                        // Calculate line number
                        $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;
                        $context = trim($lines[$lineNumber - 1] ?? '');

                        // Store with usage information
                        if (! $keys->has($key)) {
                            $keys[$key] = [];
                        }

                        $current = $keys[$key];
                        $current[] = [
                            'file' => $filePath,
                            'line' => $lineNumber,
                            'context' => $this->truncateContext($context),
                        ];
                        $keys[$key] = $current;
                    }
                }
            }
        } catch (\Exception $e) {
            // File not readable, skip
        }

        return $keys;
    }

    /**
     * Load defined translation keys from language files.
     *
     * @param  string  $langPath  Path to language directory
     * @param  array<string>  $locales  Locales to scan
     * @param  string|null  $namespace  Optional namespace filter
     * @return array<string, array<string, array<string>>> [locale => [key => [file]]]
     */
    public function loadDefinedKeys(string $langPath, array $locales, ?string $namespace = null): array
    {
        $defined = [];

        foreach ($locales as $locale) {
            $localePath = $langPath.'/'.$locale;
            $defined[$locale] = [];

            if (! is_dir($localePath)) {
                continue;
            }

            // Load PHP translation files
            foreach (File::glob($localePath.'/*.php') as $file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                $prefix = $namespace ? "{$namespace}::{$filename}" : $filename;

                try {
                    $translations = require $file;
                    if (is_array($translations)) {
                        $keys = $this->flattenTranslations($translations, $prefix);
                        foreach ($keys as $key) {
                            if (! isset($defined[$locale][$key])) {
                                $defined[$locale][$key] = [];
                            }
                            $defined[$locale][$key][] = $file;
                        }
                    }
                } catch (\Exception $e) {
                    // Invalid translation file, skip
                }
            }

            // Load JSON translation files
            foreach (File::glob($localePath.'/*.json') as $file) {
                try {
                    $translations = json_decode(File::get($file), true);
                    if (is_array($translations)) {
                        foreach (array_keys($translations) as $key) {
                            $fullKey = $namespace ? "{$namespace}::{$key}" : $key;
                            if (! isset($defined[$locale][$fullKey])) {
                                $defined[$locale][$fullKey] = [];
                            }
                            $defined[$locale][$fullKey][] = $file;
                        }
                    }
                } catch (\Exception $e) {
                    // Invalid JSON file, skip
                }
            }
        }

        return $defined;
    }

    /**
     * Flatten nested translation array to dot-notation keys.
     *
     * @param  array<string, mixed>  $translations
     * @return array<string>
     */
    protected function flattenTranslations(array $translations, string $prefix = ''): array
    {
        $keys = [];

        foreach ($translations as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenTranslations($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }

        return $keys;
    }

    /**
     * Generate the coverage report.
     *
     * @param  Collection<string, array>  $usedKeys
     * @param  array<string, array<string, array<string>>>  $definedKeys
     * @param  array<string>  $locales
     */
    protected function generateReport(Collection $usedKeys, array $definedKeys, array $locales): TranslationCoverageReport
    {
        $report = new TranslationCoverageReport;

        // Get all used key names
        $usedKeyNames = $usedKeys->keys()->all();

        foreach ($locales as $locale) {
            $localeDefined = $definedKeys[$locale] ?? [];
            $definedKeyNames = array_keys($localeDefined);

            // Missing keys: used in code but not defined
            $missing = array_diff($usedKeyNames, $definedKeyNames);
            foreach ($missing as $key) {
                $report->addMissing($locale, $key, $usedKeys[$key] ?? []);
            }

            // Unused keys: defined but not used in code
            $unused = array_diff($definedKeyNames, $usedKeyNames);
            foreach ($unused as $key) {
                $report->addUnused($locale, $key, $localeDefined[$key] ?? []);
            }

            // Coverage stats
            $totalDefined = count($definedKeyNames);
            $totalUsed = count(array_intersect($definedKeyNames, $usedKeyNames));
            $report->setStats($locale, [
                'total_defined' => $totalDefined,
                'total_used' => $totalUsed,
                'total_missing' => count($missing),
                'total_unused' => count($unused),
                'coverage' => $totalDefined > 0
                    ? round(($totalUsed / $totalDefined) * 100, 2)
                    : 100.0,
            ]);
        }

        // Add usage info
        foreach ($usedKeys as $key => $usages) {
            $report->addUsage($key, $usages);
        }

        return $report;
    }

    /**
     * Get default code paths to scan.
     *
     * @return array<string>
     */
    protected function getDefaultCodePaths(): array
    {
        return array_filter([
            app_path(),
            resource_path('views'),
            resource_path('js'),
            base_path('packages'),
            base_path('src'),
        ], fn ($path) => is_dir($path));
    }

    /**
     * Detect available locales from language directory.
     *
     * @return array<string>
     */
    protected function detectLocales(string $langPath): array
    {
        if (! is_dir($langPath)) {
            return [];
        }

        $locales = [];

        foreach (File::directories($langPath) as $dir) {
            $locale = basename($dir);
            // Skip vendor directory
            if ($locale !== 'vendor') {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    /**
     * Check if a path should be excluded from scanning.
     */
    protected function shouldExcludePath(string $path): bool
    {
        foreach ($this->excludePaths as $exclude) {
            if (str_contains($path, DIRECTORY_SEPARATOR.$exclude.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a key should be excluded from reporting.
     */
    protected function shouldExcludeKey(string $key): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (preg_match($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a file has a valid extension for scanning.
     */
    protected function hasValidExtension(string $filePath): bool
    {
        foreach (self::EXTENSIONS as $ext) {
            if (str_ends_with(strtolower($filePath), '.'.$ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Truncate context for storage.
     */
    protected function truncateContext(string $context): string
    {
        if (strlen($context) > 200) {
            return substr($context, 0, 197).'...';
        }

        return $context;
    }
}
