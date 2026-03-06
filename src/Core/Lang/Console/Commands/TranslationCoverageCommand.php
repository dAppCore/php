<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Lang\Console\Commands;

use Core\Lang\Coverage\TranslationCoverage;
use Illuminate\Console\Command;

/**
 * Report translation coverage across the codebase.
 *
 * Scans code for translation key usage and compares against translation files
 * to identify missing and unused keys.
 *
 * Usage:
 *   php artisan lang:coverage
 *   php artisan lang:coverage --locale=en_GB
 *   php artisan lang:coverage --missing --verbose
 *   php artisan lang:coverage --json > report.json
 */
class TranslationCoverageCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lang:coverage
                            {--locale= : Analyze specific locale only}
                            {--path= : Additional code path to scan}
                            {--lang-path= : Custom language directory path}
                            {--missing : Show only missing keys}
                            {--unused : Show only unused keys}
                            {--json : Output as JSON}
                            {--verbose : Show detailed usage information}';

    /**
     * The console command description.
     */
    protected $description = 'Report translation coverage - find missing and unused translation keys';

    /**
     * Execute the console command.
     */
    public function handle(TranslationCoverage $coverage): int
    {
        $this->newLine();
        $this->components->info('Translation Coverage Analysis');
        $this->newLine();

        // Build options
        $options = [];

        if ($langPath = $this->option('lang-path')) {
            $options['lang_path'] = $langPath;
        }

        if ($locale = $this->option('locale')) {
            $options['locales'] = [$locale];
        }

        if ($additionalPath = $this->option('path')) {
            $options['code_paths'] = array_filter([
                app_path(),
                resource_path('views'),
                resource_path('js'),
                base_path('packages'),
                base_path('src'),
                $additionalPath,
            ], fn ($p) => is_dir($p));
        }

        // Run analysis with progress indication
        $report = null;
        $this->components->task('Scanning code for translation keys', function () use ($coverage, $options, &$report) {
            $report = $coverage->analyze($options);

            return true;
        });

        $this->newLine();

        // Output based on format
        if ($this->option('json')) {
            $this->line($report->toJson());

            return $report->hasIssues() ? self::FAILURE : self::SUCCESS;
        }

        // Display summary
        $summary = $report->getSummary();
        $this->displaySummary($summary);

        // Display per-locale stats
        $this->displayLocaleStats($report);

        // Display issues
        $showMissing = ! $this->option('unused');
        $showUnused = ! $this->option('missing');
        $verbose = $this->option('verbose');

        if ($showMissing && $report->hasMissing()) {
            $this->displayMissingKeys($report, $verbose);
        }

        if ($showUnused && $report->hasUnused()) {
            $this->displayUnusedKeys($report, $verbose);
        }

        // Final status
        $this->newLine();
        if ($report->hasIssues()) {
            $this->components->warn('Translation coverage issues found. See details above.');

            return self::FAILURE;
        }

        $this->components->info('No translation coverage issues found.');

        return self::SUCCESS;
    }

    /**
     * Display the summary section.
     *
     * @param  array{locales: int, total_coverage: float, total_missing: int, total_unused: int, has_issues: bool}  $summary
     */
    protected function displaySummary(array $summary): void
    {
        $this->components->twoColumnDetail('<fg=gray;options=bold>Summary</>', '');

        $this->components->twoColumnDetail(
            'Locales analyzed',
            "<fg=cyan>{$summary['locales']}</>"
        );

        $coverageColor = $summary['total_coverage'] >= 90 ? 'green' : ($summary['total_coverage'] >= 70 ? 'yellow' : 'red');
        $this->components->twoColumnDetail(
            'Overall coverage',
            "<fg={$coverageColor}>{$summary['total_coverage']}%</>"
        );

        $missingColor = $summary['total_missing'] === 0 ? 'green' : 'yellow';
        $this->components->twoColumnDetail(
            'Missing keys',
            "<fg={$missingColor}>{$summary['total_missing']}</>"
        );

        $unusedColor = $summary['total_unused'] === 0 ? 'green' : 'yellow';
        $this->components->twoColumnDetail(
            'Unused keys',
            "<fg={$unusedColor}>{$summary['total_unused']}</>"
        );

        $this->newLine();
    }

    /**
     * Display per-locale statistics.
     *
     * @param  \Core\Lang\Coverage\TranslationCoverageReport  $report
     */
    protected function displayLocaleStats($report): void
    {
        $locales = $report->getLocales();

        if (empty($locales)) {
            return;
        }

        $this->components->twoColumnDetail('<fg=gray;options=bold>Per-Locale Statistics</>', '');

        foreach ($locales as $locale) {
            $stats = $report->getStats($locale);

            $coverageColor = $stats['coverage'] >= 90 ? 'green' : ($stats['coverage'] >= 70 ? 'yellow' : 'red');

            $this->components->twoColumnDetail(
                $locale,
                sprintf(
                    '<fg=%s>%.1f%%</> (defined: %d, used: %d, missing: %d, unused: %d)',
                    $coverageColor,
                    $stats['coverage'],
                    $stats['total_defined'],
                    $stats['total_used'],
                    $stats['total_missing'],
                    $stats['total_unused']
                )
            );
        }

        $this->newLine();
    }

    /**
     * Display missing keys.
     *
     * @param  \Core\Lang\Coverage\TranslationCoverageReport  $report
     */
    protected function displayMissingKeys($report, bool $verbose): void
    {
        $this->components->twoColumnDetail('<fg=yellow;options=bold>Missing Keys</>', '');
        $this->line('  <fg=gray>Keys used in code but not defined in translation files:</>');
        $this->newLine();

        foreach ($report->getLocales() as $locale) {
            $missing = $report->getMissing($locale);

            if (empty($missing)) {
                continue;
            }

            $this->line("  <fg=cyan;options=bold>{$locale}:</>");

            foreach ($missing as $key => $usages) {
                $this->line("    - <fg=yellow>{$key}</>");

                if ($verbose && ! empty($usages)) {
                    foreach (array_slice($usages, 0, 3) as $usage) {
                        $shortPath = $this->shortenPath($usage['file']);
                        $this->line("      <fg=gray>Used in: {$shortPath}:{$usage['line']}</>");
                    }

                    if (count($usages) > 3) {
                        $remaining = count($usages) - 3;
                        $this->line("      <fg=gray>... and {$remaining} more usages</>");
                    }
                }
            }

            $this->newLine();
        }
    }

    /**
     * Display unused keys.
     *
     * @param  \Core\Lang\Coverage\TranslationCoverageReport  $report
     */
    protected function displayUnusedKeys($report, bool $verbose): void
    {
        $this->components->twoColumnDetail('<fg=blue;options=bold>Unused Keys</>', '');
        $this->line('  <fg=gray>Keys defined in translation files but not used in code:</>');
        $this->newLine();

        foreach ($report->getLocales() as $locale) {
            $unused = $report->getUnused($locale);

            if (empty($unused)) {
                continue;
            }

            $this->line("  <fg=cyan;options=bold>{$locale}:</>");

            foreach ($unused as $key => $files) {
                $this->line("    - <fg=blue>{$key}</>");

                if ($verbose && ! empty($files)) {
                    foreach ($files as $file) {
                        $shortPath = $this->shortenPath($file);
                        $this->line("      <fg=gray>Defined in: {$shortPath}</>");
                    }
                }
            }

            $this->newLine();
        }
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
     * Get shell completion suggestions.
     */
    public function complete(
        \Symfony\Component\Console\Completion\CompletionInput $input,
        \Symfony\Component\Console\Completion\CompletionSuggestions $suggestions
    ): void {
        if ($input->mustSuggestOptionValuesFor('locale')) {
            // Suggest available locales
            $langPath = lang_path();
            if (is_dir($langPath)) {
                $locales = [];
                foreach (scandir($langPath) as $item) {
                    if ($item !== '.' && $item !== '..' && $item !== 'vendor' && is_dir($langPath.'/'.$item)) {
                        $locales[] = $item;
                    }
                }
                $suggestions->suggestValues($locales);
            }
        }

        if ($input->mustSuggestOptionValuesFor('path')) {
            // Suggest common paths
            $suggestions->suggestValues([
                app_path(),
                resource_path('views'),
                resource_path('js'),
                base_path('packages'),
            ]);
        }
    }
}
