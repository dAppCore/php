<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Lang\Console\Commands;

use Core\Lang\TranslationMemory\TranslationMemory;
use Illuminate\Console\Command;

/**
 * Translation Memory management command.
 *
 * Provides CLI interface for managing translation memory including:
 * - Viewing statistics and entries
 * - Importing/exporting TMX files
 * - Searching and suggesting translations
 * - Clearing entries
 *
 * Usage:
 *   php artisan lang:tm stats                      # Show statistics
 *   php artisan lang:tm import memory.tmx         # Import TMX file
 *   php artisan lang:tm export memory.tmx en_GB de_DE  # Export to TMX
 *   php artisan lang:tm search "hello"            # Search translations
 *   php artisan lang:tm suggest "hello world"     # Get suggestions
 *   php artisan lang:tm clear en_GB de_DE         # Clear locale pair
 */
class TranslationMemoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lang:tm
                            {action=stats : Action to perform (stats, list, import, export, search, suggest, clear, validate)}
                            {argument? : File path, search query, or source locale}
                            {argument2? : Target locale or additional argument}
                            {--source= : Source locale filter}
                            {--target= : Target locale filter}
                            {--min-quality= : Minimum quality filter}
                            {--min-similarity=0.6 : Minimum similarity for suggestions}
                            {--max=20 : Maximum results to show}
                            {--json : Output as JSON}
                            {--include-metadata : Include metadata in export}
                            {--skip-existing : Skip existing entries during import}';

    /**
     * The console command description.
     */
    protected $description = 'Manage translation memory - import, export, search, and analyze';

    /**
     * Execute the console command.
     */
    public function handle(TranslationMemory $tm): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'stats' => $this->handleStats($tm),
            'list' => $this->handleList($tm),
            'import' => $this->handleImport($tm),
            'export' => $this->handleExport($tm),
            'search' => $this->handleSearch($tm),
            'suggest' => $this->handleSuggest($tm),
            'clear' => $this->handleClear($tm),
            'validate' => $this->handleValidate($tm),
            default => $this->showHelp(),
        };
    }

    /**
     * Show translation memory statistics.
     */
    protected function handleStats(TranslationMemory $tm): int
    {
        $stats = $tm->getStats();

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info('Translation Memory Statistics');
        $this->newLine();

        $this->components->twoColumnDetail('Total entries', (string) $stats['total_entries']);
        $this->components->twoColumnDetail('Locale pairs', (string) $stats['locale_pairs']);
        $this->components->twoColumnDetail('Average quality', sprintf('%.1f%%', $stats['avg_quality'] * 100));
        $this->components->twoColumnDetail('High quality entries', (string) $stats['high_quality_count']);
        $this->components->twoColumnDetail('Needs review', (string) $stats['needs_review_count']);
        $this->components->twoColumnDetail('Total usage count', (string) $stats['total_usage']);

        // Show locale pairs
        $pairs = $tm->getLocalePairs();
        if (! empty($pairs)) {
            $this->newLine();
            $this->components->twoColumnDetail('<fg=gray;options=bold>Locale Pairs</>', '');

            foreach ($pairs as $pair) {
                $count = $tm->count($pair['source'], $pair['target']);
                $this->components->twoColumnDetail(
                    "{$pair['source']} -> {$pair['target']}",
                    "{$count} entries"
                );
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * List translation memory entries.
     */
    protected function handleList(TranslationMemory $tm): int
    {
        $sourceLocale = $this->option('source') ?? $this->argument('argument');
        $targetLocale = $this->option('target') ?? $this->argument('argument2');
        $max = (int) $this->option('max');

        if ($sourceLocale && $targetLocale) {
            $entries = $tm->getByLocalePair($sourceLocale, $targetLocale);
        } else {
            $entries = $tm->getRepository()->all();
        }

        if (isset($this->options()['min-quality']) && $this->option('min-quality') !== null) {
            $minQuality = (float) $this->option('min-quality');
            $entries = $entries->filter(fn ($e) => $e->getQuality() >= $minQuality);
        }

        $entries = $entries->take($max);

        if ($this->option('json')) {
            $this->line(json_encode($entries->toArray(), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info('Translation Memory Entries');
        $this->newLine();

        if ($entries->isEmpty()) {
            $this->components->warn('No entries found');

            return self::SUCCESS;
        }

        $this->table(
            ['Source', 'Target', 'Locales', 'Quality', 'Usage'],
            $entries->map(fn ($e) => [
                $this->truncate($e->getSource(), 40),
                $this->truncate($e->getTarget(), 40),
                "{$e->getSourceLocale()}->{$e->getTargetLocale()}",
                sprintf('%.0f%%', $e->getQuality() * 100),
                $e->getUsageCount(),
            ])->all()
        );

        $this->newLine();
        $this->line("  <fg=gray>Showing {$entries->count()} of {$tm->count()} entries</>");
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Import translations from a TMX file.
     */
    protected function handleImport(TranslationMemory $tm): int
    {
        $filePath = $this->argument('argument');

        if (empty($filePath)) {
            $this->components->error('Please specify a TMX file path');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info("Importing from: {$filePath}");

        $options = [
            'skip_existing' => $this->option('skip-existing'),
        ];

        if ($this->option('source')) {
            $options['source_locale'] = $this->option('source');
        }

        if ($this->option('target')) {
            $options['target_locale'] = $this->option('target');
        }

        if ($this->option('min-quality')) {
            $options['default_quality'] = (float) $this->option('min-quality');
        }

        $result = $tm->importTmx($filePath, $options);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();

        if (! empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Imported', (string) $result['imported']);
        $this->components->twoColumnDetail('Skipped', (string) $result['skipped']);
        $this->components->twoColumnDetail('Locales found', implode(', ', $result['locales_found']));

        $this->newLine();
        $this->components->info('Import completed successfully');

        return self::SUCCESS;
    }

    /**
     * Export translations to a TMX file.
     */
    protected function handleExport(TranslationMemory $tm): int
    {
        $filePath = $this->argument('argument');
        $sourceLocale = $this->argument('argument2') ?? $this->option('source');
        $targetLocale = $this->option('target');

        if (empty($filePath)) {
            $this->components->error('Please specify an output file path');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info("Exporting to: {$filePath}");

        $options = [
            'include_metadata' => $this->option('include-metadata'),
        ];

        if ($this->option('min-quality')) {
            $options['min_quality'] = (float) $this->option('min-quality');
        }

        if ($sourceLocale && $targetLocale) {
            $result = $tm->exportTmx($filePath, $sourceLocale, $targetLocale, $options);
        } else {
            $result = $tm->exportAllTmx($filePath, $options);
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();

        if (! $result['success']) {
            $this->components->error($result['error'] ?? 'Export failed');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Exported', (string) $result['exported']);
        $this->components->twoColumnDetail('File size', $this->formatBytes($result['file_size']));

        $this->newLine();
        $this->components->info('Export completed successfully');

        return self::SUCCESS;
    }

    /**
     * Search translation memory.
     */
    protected function handleSearch(TranslationMemory $tm): int
    {
        $query = $this->argument('argument');

        if (empty($query)) {
            $this->components->error('Please specify a search query');

            return self::FAILURE;
        }

        $results = $tm->search(
            $query,
            $this->option('source'),
            $this->option('target'),
            (int) $this->option('max')
        );

        if ($this->option('json')) {
            $this->line(json_encode($results->toArray(), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info("Search results for: {$query}");
        $this->newLine();

        if ($results->isEmpty()) {
            $this->components->warn('No results found');

            return self::SUCCESS;
        }

        $this->table(
            ['Source', 'Target', 'Locales', 'Quality'],
            $results->map(fn ($e) => [
                $this->truncate($e->getSource(), 45),
                $this->truncate($e->getTarget(), 45),
                "{$e->getSourceLocale()}->{$e->getTargetLocale()}",
                sprintf('%.0f%%', $e->getQuality() * 100),
            ])->all()
        );

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Suggest translations using fuzzy matching.
     */
    protected function handleSuggest(TranslationMemory $tm): int
    {
        $source = $this->argument('argument');
        $sourceLocale = $this->option('source') ?? 'en_GB';
        $targetLocale = $this->option('target') ?? 'de_DE';

        if (empty($source)) {
            $this->components->error('Please specify source text');

            return self::FAILURE;
        }

        $minSimilarity = (float) $this->option('min-similarity');
        $max = (int) $this->option('max');

        $suggestions = $tm->suggest($source, $sourceLocale, $targetLocale, $minSimilarity, $max);

        if ($this->option('json')) {
            $this->line(json_encode($suggestions->map(fn ($s) => [
                'source' => $s['entry']->getSource(),
                'target' => $s['entry']->getTarget(),
                'similarity' => $s['similarity'],
                'confidence' => $s['confidence'],
            ])->toArray(), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info("Suggestions for: {$source}");
        $this->line("  <fg=gray>{$sourceLocale} -> {$targetLocale}</>");
        $this->newLine();

        if ($suggestions->isEmpty()) {
            $this->components->warn('No suggestions found');

            return self::SUCCESS;
        }

        foreach ($suggestions as $suggestion) {
            $entry = $suggestion['entry'];
            $similarity = $suggestion['similarity'];
            $confidence = $suggestion['confidence'];

            $similarityColor = $similarity >= 0.9 ? 'green' : ($similarity >= 0.75 ? 'yellow' : 'red');
            $category = \Core\Lang\TranslationMemory\FuzzyMatcher::categorizeSimilarity($similarity);

            $this->line("  <fg={$similarityColor};options=bold>".sprintf('%.0f%%', $similarity * 100).'</> match ('.$category.')');
            $this->line("    <fg=cyan>Source:</> {$entry->getSource()}");
            $this->line("    <fg=cyan>Target:</> {$entry->getTarget()}");
            $this->line('    <fg=gray>Confidence: '.sprintf('%.0f%%', $confidence * 100).', Quality: '.sprintf('%.0f%%', $entry->getQuality() * 100).'</>');
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * Clear translation memory entries.
     */
    protected function handleClear(TranslationMemory $tm): int
    {
        $sourceLocale = $this->argument('argument') ?? $this->option('source');
        $targetLocale = $this->argument('argument2') ?? $this->option('target');

        if ($sourceLocale && $targetLocale) {
            $count = $tm->count($sourceLocale, $targetLocale);

            if ($count === 0) {
                $this->components->warn("No entries found for {$sourceLocale} -> {$targetLocale}");

                return self::SUCCESS;
            }

            if (! $this->confirm("Delete {$count} entries for {$sourceLocale} -> {$targetLocale}?")) {
                $this->components->info('Cancelled');

                return self::SUCCESS;
            }

            $deleted = $tm->clearLocalePair($sourceLocale, $targetLocale);
            $this->components->info("Deleted {$deleted} entries");
        } else {
            $count = $tm->count();

            if ($count === 0) {
                $this->components->warn('Translation memory is already empty');

                return self::SUCCESS;
            }

            if (! $this->confirm("Delete ALL {$count} entries from translation memory?")) {
                $this->components->info('Cancelled');

                return self::SUCCESS;
            }

            $deleted = $tm->clearAll();
            $this->components->info("Deleted {$deleted} entries");
        }

        return self::SUCCESS;
    }

    /**
     * Validate a TMX file.
     */
    protected function handleValidate(TranslationMemory $tm): int
    {
        $filePath = $this->argument('argument');

        if (empty($filePath)) {
            $this->components->error('Please specify a TMX file path');

            return self::FAILURE;
        }

        $result = $tm->validateTmx($filePath);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return $result['valid'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->components->info("Validating: {$filePath}");
        $this->newLine();

        if (! $result['valid']) {
            foreach ($result['errors'] as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Valid', '<fg=green>Yes</>');
        $this->components->twoColumnDetail('TMX Version', $result['version'] ?? 'Unknown');
        $this->components->twoColumnDetail('Entry count', (string) $result['entry_count']);
        $this->components->twoColumnDetail('Locales', implode(', ', $result['locales']));

        $this->newLine();
        $this->components->info('Validation passed');

        return self::SUCCESS;
    }

    /**
     * Show help information.
     */
    protected function showHelp(): int
    {
        $this->newLine();
        $this->components->info('Translation Memory Commands');
        $this->newLine();

        $this->line('  <fg=cyan>stats</>                          Show translation memory statistics');
        $this->line('  <fg=cyan>list</>                           List entries (use --source/--target to filter)');
        $this->line('  <fg=cyan>import</> <file.tmx>               Import from TMX file');
        $this->line('  <fg=cyan>export</> <file.tmx> [source] [target]  Export to TMX file');
        $this->line('  <fg=cyan>search</> <query>                  Search translations');
        $this->line('  <fg=cyan>suggest</> <text>                  Get translation suggestions');
        $this->line('  <fg=cyan>clear</> [source] [target]        Clear entries');
        $this->line('  <fg=cyan>validate</> <file.tmx>            Validate TMX file');

        $this->newLine();
        $this->line('  <fg=gray>Options:</>');
        $this->line('    --source=LOCALE        Filter by source locale');
        $this->line('    --target=LOCALE        Filter by target locale');
        $this->line('    --min-quality=N        Minimum quality score (0.0-1.0)');
        $this->line('    --min-similarity=N     Minimum similarity for suggestions (default: 0.6)');
        $this->line('    --max=N                Maximum results (default: 20)');
        $this->line('    --json                 Output as JSON');
        $this->line('    --include-metadata     Include metadata in TMX export');
        $this->line('    --skip-existing        Skip existing entries during import');

        $this->newLine();
        $this->line('  <fg=gray>Examples:</>');
        $this->line('    php artisan lang:tm stats');
        $this->line('    php artisan lang:tm list --source=en_GB --target=de_DE');
        $this->line('    php artisan lang:tm import translations.tmx --skip-existing');
        $this->line('    php artisan lang:tm export output.tmx en_GB de_DE');
        $this->line('    php artisan lang:tm search "welcome" --source=en_GB');
        $this->line('    php artisan lang:tm suggest "Hello world" --source=en_GB --target=de_DE');

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Truncate a string for display.
     */
    protected function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3).'...';
    }

    /**
     * Format bytes as human-readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }

    /**
     * Get shell completion suggestions.
     */
    public function complete(
        \Symfony\Component\Console\Completion\CompletionInput $input,
        \Symfony\Component\Console\Completion\CompletionSuggestions $suggestions
    ): void {
        if ($input->mustSuggestArgumentValuesFor('action')) {
            $suggestions->suggestValues([
                'stats',
                'list',
                'import',
                'export',
                'search',
                'suggest',
                'clear',
                'validate',
            ]);
        }

        if ($input->mustSuggestOptionValuesFor('source') || $input->mustSuggestOptionValuesFor('target')) {
            // Suggest common locales
            $suggestions->suggestValues([
                'en_GB',
                'en_US',
                'de_DE',
                'fr_FR',
                'es_ES',
                'it_IT',
                'nl_NL',
                'pt_PT',
                'pt_BR',
                'ja_JP',
                'zh_CN',
                'ko_KR',
            ]);
        }
    }
}
