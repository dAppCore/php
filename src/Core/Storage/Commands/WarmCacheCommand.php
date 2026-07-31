<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Storage\Commands;

use Core\Storage\CacheWarmer;
use Illuminate\Console\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

/**
 * Warm registered cache items.
 *
 * Pre-populates the cache with frequently accessed data to prevent
 * cold cache problems after deployments or cache flushes.
 *
 * Usage:
 *   php artisan cache:warm           # Warm all registered items
 *   php artisan cache:warm --stale   # Only warm stale/missing items
 *   php artisan cache:warm --status  # Show warming status
 *   php artisan cache:warm --key=foo # Warm specific key
 *
 * Scheduling (in app/Console/Kernel.php):
 *   $schedule->command('cache:warm --stale')->hourly();
 */
class WarmCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:warm
                            {--stale : Only warm stale (missing/expired) items}
                            {--key= : Warm a specific key only}
                            {--status : Show warming status without warming}
                            {--store= : Use a specific cache store}';

    /**
     * The console command description.
     */
    protected $description = 'Warm registered cache items to prevent cold cache problems';

    /**
     * Execute the console command.
     */
    public function handle(CacheWarmer $warmer): int
    {
        $this->newLine();
        $this->components->info('Cache Warming');
        $this->newLine();

        // Configure store if specified
        if ($store = $this->option('store')) {
            $warmer->useStore($store);
            $this->components->twoColumnDetail('Using store', sprintf('<fg=cyan>%s</>', $store));
        }

        // Status mode
        if ($this->option('status')) {
            return $this->showStatus($warmer);
        }

        // Single key mode
        if ($key = $this->option('key')) {
            return $this->warmSingleKey($warmer, $key);
        }

        // Check if any items are registered
        $registeredKeys = $warmer->getRegisteredKeys();
        if ($registeredKeys === []) {
            $this->components->warn('No cache items registered for warming.');
            $this->newLine();
            $this->components->bulletList([
                'Register items in a service provider using CacheWarmer::register()',
                'Example: $warmer->register(\'config\', fn() => Config::all());',
            ]);
            $this->newLine();

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Registered items', '<fg=cyan>'.count($registeredKeys).'</>');
        $this->newLine();

        // Warm items
        $staleOnly = $this->option('stale');
        $results = $staleOnly
            ? $warmer->warmStale()
            : $warmer->warmAll();

        // Display results
        $this->displayResults($results, $staleOnly);

        return self::SUCCESS;
    }

    /**
     * Show warming status without warming.
     */
    protected function showStatus(CacheWarmer $warmer): int
    {
        $status = $warmer->getStatus();

        if ($status === []) {
            $this->components->warn('No cache items registered for warming.');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('<fg=gray;options=bold>Registered Items</>', '');

        foreach ($status as $key => $info) {
            $cachedStatus = match ($info['cached']) {
                true => '<fg=green>cached</>',
                false => '<fg=yellow>not cached</>',
                null => '<fg=gray>batch</>',
            };

            $typeLabel = $info['type'] === 'batch'
                ? sprintf('[batch:%s]', $info['batch_size'])
                : '';

            $this->components->twoColumnDetail(
                sprintf('<fg=cyan>%s</> %s', $key, $typeLabel),
                sprintf('%s (TTL: %ss)', $cachedStatus, $info['ttl'])
            );
        }

        $this->newLine();

        // Show stats summary
        $stats = $warmer->getStats();
        $this->components->twoColumnDetail('<fg=gray;options=bold>Summary</>', '');
        $this->components->twoColumnDetail('Registered', sprintf('<fg=cyan>%d</>', $stats['total_registered']));
        $this->components->twoColumnDetail('Cached', sprintf('<fg=cyan>%d</>', $stats['total_cached']));
        $this->components->twoColumnDetail('Cache rate', sprintf('<fg=cyan>%s%%</>', $stats['cache_rate']));
        if ($stats['batch_items'] > 0) {
            $this->components->twoColumnDetail('Batch items', sprintf('<fg=cyan>%s</>', $stats['batch_items']));
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Warm a single key.
     */
    protected function warmSingleKey(CacheWarmer $warmer, string $key): int
    {
        if (! $warmer->isRegistered($key)) {
            $this->components->error(sprintf("Key '%s' is not registered for warming.", $key));
            $this->newLine();

            // Show available keys
            $availableKeys = $warmer->getRegisteredKeys();
            if ($availableKeys !== []) {
                $this->components->info('Available keys:');
                $this->components->bulletList($availableKeys);
                $this->newLine();
            }

            return self::FAILURE;
        }

        $this->components->task(
            'Warming key: ' . $key,
            fn () => $warmer->warm($key)
        );

        $this->newLine();

        $results = $warmer->getLastResults();
        if (isset($results[$key])) {
            $result = $results[$key];
            if ($result['status'] === 'success') {
                $this->components->info(sprintf("Successfully warmed '%s' in %ss", $key, $result['duration']));
            } else {
                $this->components->error(sprintf("Failed to warm '%s': %s", $key, $result['error']));

                return self::FAILURE;
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Display warming results.
     *
     * @param  array<string, array{status: string, duration: float, error?: string}>  $results
     */
    protected function displayResults(array $results, bool $staleOnly): void
    {
        $successes = 0;
        $failures = 0;
        $skipped = 0;
        $totalDuration = 0.0;

        $this->components->twoColumnDetail('<fg=gray;options=bold>Results</>', '');

        foreach ($results as $key => $result) {
            if ($key === '_disabled') {
                $this->components->warn('Cache warming is disabled.');
                $this->newLine();

                return;
            }

            $statusLabel = match ($result['status']) {
                'success' => '<fg=green>warmed</>',
                'failed' => '<fg=red>failed</>',
                'exists' => '<fg=gray>exists</>',
                'skipped' => '<fg=gray>skipped</>',
                default => sprintf('<fg=yellow>%s</>', $result['status']),
            };

            $duration = $result['duration'] > 0
                ? sprintf('%.3fs', $result['duration'])
                : '';

            $this->components->twoColumnDetail(
                sprintf('<fg=cyan>%s</>', $key),
                sprintf('%s %s', $statusLabel, $duration)
            );

            if (isset($result['error'])) {
                $this->line(sprintf('  <fg=red>Error: %s</>', $result['error']));
            }

            match ($result['status']) {
                'success' => $successes++,
                'failed' => $failures++,
                'exists', 'skipped' => $skipped++,
                default => null,
            };

            $totalDuration += $result['duration'];
        }

        $this->newLine();

        // Summary
        $this->components->twoColumnDetail('<fg=gray;options=bold>Summary</>', '');
        $this->components->twoColumnDetail('Warmed', sprintf('<fg=green>%d</>', $successes));
        if ($failures > 0) {
            $this->components->twoColumnDetail('Failed', sprintf('<fg=red>%d</>', $failures));
        }

        if ($staleOnly && $skipped > 0) {
            $this->components->twoColumnDetail('Already cached', sprintf('<fg=gray>%d</>', $skipped));
        }

        $this->components->twoColumnDetail('Total time', sprintf('<fg=cyan>%.3fs</>', $totalDuration));
        $this->newLine();

        if ($failures > 0) {
            $this->components->warn('Some items failed to warm. Check the logs for details.');
            $this->newLine();
        }
    }

    /**
     * Get shell completion suggestions for options.
     */
    public function complete(
        CompletionInput $input,
        CompletionSuggestions $suggestions
    ): void {
        if ($input->mustSuggestOptionValuesFor('store')) {
            // Suggest common cache stores
            $suggestions->suggestValues(['file', 'redis', 'database', 'array', 'memcached']);
        }

        if ($input->mustSuggestOptionValuesFor('key')) {
            // Suggest registered keys
            try {
                $warmer = app(CacheWarmer::class);
                $suggestions->suggestValues($warmer->getRegisteredKeys());
            } catch (\Throwable) {
                // Ignore if container not available
            }
        }
    }
}
