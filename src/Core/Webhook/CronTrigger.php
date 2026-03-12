<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Webhook;

use Core\Actions\Action;
use Core\Actions\Scheduled;
use Illuminate\Support\Facades\Http;

#[Scheduled(frequency: 'everyMinute', withoutOverlapping: true, runInBackground: true)]
class CronTrigger
{
    use Action;

    public function handle(): void
    {
        $triggers = config('webhook.cron_triggers', []);

        foreach ($triggers as $product => $config) {
            if (empty($config['base_url'])) {
                continue;
            }

            $baseUrl = rtrim($config['base_url'], '/');
            $key = $config['key'] ?? '';
            $stagger = (int) ($config['stagger_seconds'] ?? 0);
            $offset = (int) ($config['offset_seconds'] ?? 0);

            if ($offset > 0) {
                usleep($offset * 1_000_000);
            }

            foreach ($config['endpoints'] ?? [] as $i => $endpoint) {
                if ($i > 0 && $stagger > 0) {
                    usleep($stagger * 1_000_000);
                }

                $url = $baseUrl . $endpoint . '?key=' . $key;

                try {
                    Http::timeout(30)->get($url);
                } catch (\Throwable $e) {
                    logger()->warning("Cron trigger failed for {$product}{$endpoint}: {$e->getMessage()}");
                }
            }
        }
    }
}
