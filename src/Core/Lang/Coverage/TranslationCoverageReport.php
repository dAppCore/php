<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Lang\Coverage;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Translation Coverage Report.
 *
 * Contains the results of a translation coverage analysis including:
 * - Missing keys (used in code but not defined in translation files)
 * - Unused keys (defined but not used in code)
 * - Coverage statistics per locale
 * - Usage information showing where keys are used
 *
 * @implements Arrayable<string, mixed>
 */
class TranslationCoverageReport implements Arrayable
{
    /**
     * Missing translation keys per locale.
     *
     * @var array<string, array<string, array>>
     */
    protected array $missing = [];

    /**
     * Unused translation keys per locale.
     *
     * @var array<string, array<string, array>>
     */
    protected array $unused = [];

    /**
     * Coverage statistics per locale.
     *
     * @var array<string, array{total_defined: int, total_used: int, total_missing: int, total_unused: int, coverage: float}>
     */
    protected array $stats = [];

    /**
     * Key usage information.
     *
     * @var array<string, array<array{file: string, line: int, context: string}>>
     */
    protected array $usages = [];

    /**
     * Add a missing key for a locale.
     *
     * @param  string  $locale  The locale
     * @param  string  $key  The missing key
     * @param  array<array{file: string, line: int, context: string}>  $usages  Where the key is used
     */
    public function addMissing(string $locale, string $key, array $usages): void
    {
        if (! isset($this->missing[$locale])) {
            $this->missing[$locale] = [];
        }

        $this->missing[$locale][$key] = $usages;
    }

    /**
     * Add an unused key for a locale.
     *
     * @param  string  $locale  The locale
     * @param  string  $key  The unused key
     * @param  array<string>  $files  Files where the key is defined
     */
    public function addUnused(string $locale, string $key, array $files): void
    {
        if (! isset($this->unused[$locale])) {
            $this->unused[$locale] = [];
        }

        $this->unused[$locale][$key] = $files;
    }

    /**
     * Set coverage statistics for a locale.
     *
     * @param  string  $locale  The locale
     * @param  array{total_defined: int, total_used: int, total_missing: int, total_unused: int, coverage: float}  $stats
     */
    public function setStats(string $locale, array $stats): void
    {
        $this->stats[$locale] = $stats;
    }

    /**
     * Add usage information for a key.
     *
     * @param  string  $key  The translation key
     * @param  array<array{file: string, line: int, context: string}>  $usages  Usage locations
     */
    public function addUsage(string $key, array $usages): void
    {
        $this->usages[$key] = $usages;
    }

    /**
     * Get missing keys for a locale or all locales.
     *
     * @param  string|null  $locale  Locale to get (null for all)
     * @return array<string, array>|array<string, array<string, array>>
     */
    public function getMissing(?string $locale = null): array
    {
        if ($locale !== null) {
            return $this->missing[$locale] ?? [];
        }

        return $this->missing;
    }

    /**
     * Get unused keys for a locale or all locales.
     *
     * @param  string|null  $locale  Locale to get (null for all)
     * @return array<string, array>|array<string, array<string, array>>
     */
    public function getUnused(?string $locale = null): array
    {
        if ($locale !== null) {
            return $this->unused[$locale] ?? [];
        }

        return $this->unused;
    }

    /**
     * Get coverage statistics for a locale or all locales.
     *
     * @param  string|null  $locale  Locale to get (null for all)
     * @return array<string, int|float>|array<string, array<string, int|float>>
     */
    public function getStats(?string $locale = null): array
    {
        if ($locale !== null) {
            return $this->stats[$locale] ?? [
                'total_defined' => 0,
                'total_used' => 0,
                'total_missing' => 0,
                'total_unused' => 0,
                'coverage' => 0.0,
            ];
        }

        return $this->stats;
    }

    /**
     * Get usage information for a specific key.
     *
     * @param  string  $key  The translation key
     * @return array<array{file: string, line: int, context: string}>
     */
    public function getUsages(string $key): array
    {
        return $this->usages[$key] ?? [];
    }

    /**
     * Get all usage information.
     *
     * @return array<string, array<array{file: string, line: int, context: string}>>
     */
    public function getAllUsages(): array
    {
        return $this->usages;
    }

    /**
     * Get the total coverage percentage across all locales.
     */
    public function getTotalCoverage(): float
    {
        if (empty($this->stats)) {
            return 100.0;
        }

        $totalDefined = 0;
        $totalUsed = 0;

        foreach ($this->stats as $stat) {
            $totalDefined += $stat['total_defined'];
            $totalUsed += $stat['total_used'];
        }

        return $totalDefined > 0 ? round(($totalUsed / $totalDefined) * 100, 2) : 100.0;
    }

    /**
     * Get the total count of missing keys across all locales.
     */
    public function getTotalMissing(): int
    {
        $count = 0;

        foreach ($this->missing as $localeMissing) {
            $count += count($localeMissing);
        }

        return $count;
    }

    /**
     * Get the total count of unused keys across all locales.
     */
    public function getTotalUnused(): int
    {
        $count = 0;

        foreach ($this->unused as $localeUnused) {
            $count += count($localeUnused);
        }

        return $count;
    }

    /**
     * Get a list of all locales in the report.
     *
     * @return array<string>
     */
    public function getLocales(): array
    {
        return array_keys($this->stats);
    }

    /**
     * Check if the report has any issues (missing or unused keys).
     */
    public function hasIssues(): bool
    {
        return $this->getTotalMissing() > 0 || $this->getTotalUnused() > 0;
    }

    /**
     * Check if there are missing keys.
     */
    public function hasMissing(): bool
    {
        return $this->getTotalMissing() > 0;
    }

    /**
     * Check if there are unused keys.
     */
    public function hasUnused(): bool
    {
        return $this->getTotalUnused() > 0;
    }

    /**
     * Get a summary of the report.
     *
     * @return array{locales: int, total_coverage: float, total_missing: int, total_unused: int, has_issues: bool}
     */
    public function getSummary(): array
    {
        return [
            'locales' => count($this->stats),
            'total_coverage' => $this->getTotalCoverage(),
            'total_missing' => $this->getTotalMissing(),
            'total_unused' => $this->getTotalUnused(),
            'has_issues' => $this->hasIssues(),
        ];
    }

    /**
     * Generate a text report for console output.
     *
     * @param  bool  $verbose  Include usage details
     */
    public function toText(bool $verbose = false): string
    {
        $output = [];
        $output[] = '# Translation Coverage Report';
        $output[] = '';

        // Summary
        $summary = $this->getSummary();
        $output[] = '## Summary';
        $output[] = sprintf('  Locales: %d', $summary['locales']);
        $output[] = sprintf('  Coverage: %.1f%%', $summary['total_coverage']);
        $output[] = sprintf('  Missing: %d', $summary['total_missing']);
        $output[] = sprintf('  Unused: %d', $summary['total_unused']);
        $output[] = '';

        // Per-locale details
        foreach ($this->getLocales() as $locale) {
            $stats = $this->getStats($locale);
            $output[] = sprintf('## Locale: %s', $locale);
            $output[] = sprintf('  Defined: %d, Used: %d, Coverage: %.1f%%',
                $stats['total_defined'],
                $stats['total_used'],
                $stats['coverage']
            );

            // Missing keys
            $missing = $this->getMissing($locale);
            if (! empty($missing)) {
                $output[] = '';
                $output[] = '  ### Missing Keys:';
                foreach ($missing as $key => $usages) {
                    $output[] = sprintf('    - %s', $key);
                    if ($verbose && ! empty($usages)) {
                        foreach ($usages as $usage) {
                            $output[] = sprintf('      Used in: %s:%d',
                                $this->shortenPath($usage['file']),
                                $usage['line']
                            );
                        }
                    }
                }
            }

            // Unused keys
            $unused = $this->getUnused($locale);
            if (! empty($unused)) {
                $output[] = '';
                $output[] = '  ### Unused Keys:';
                foreach ($unused as $key => $files) {
                    $output[] = sprintf('    - %s', $key);
                    if ($verbose && ! empty($files)) {
                        foreach ($files as $file) {
                            $output[] = sprintf('      Defined in: %s', $this->shortenPath($file));
                        }
                    }
                }
            }

            $output[] = '';
        }

        return implode("\n", $output);
    }

    /**
     * Shorten a file path for display.
     */
    protected function shortenPath(string $path): string
    {
        $basePath = base_path();

        if (str_starts_with($path, $basePath)) {
            return substr($path, strlen($basePath) + 1);
        }

        return $path;
    }

    /**
     * Convert the report to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->getSummary(),
            'stats' => $this->stats,
            'missing' => $this->missing,
            'unused' => $this->unused,
            'usages' => $this->usages,
        ];
    }

    /**
     * Export the report to JSON.
     */
    public function toJson(int $flags = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toArray(), $flags);
    }
}
