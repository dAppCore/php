<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

namespace Core\Seo\Console\Commands;

use Core\Seo\Services\ServiceOgImageService;
use Illuminate\Console\Command;

/**
 * Generate Service OG Images Command
 *
 * Pre-generates Open Graph images for all service landing pages.
 * Run this after deployment or when service branding changes.
 */
class GenerateServiceOgImages extends Command
{
    protected $signature = 'og:generate-services
                            {service? : Specific service to generate (biohost, socialhost, etc.)}
                            {--force : Regenerate even if images exist}';

    protected $description = 'Generate Open Graph images for service landing pages';

    public function handle(ServiceOgImageService $ogService): int
    {
        $specificService = $this->argument('service');
        $force = $this->option('force');

        if ($specificService) {
            return $this->generateSingle($ogService, $specificService, $force);
        }

        return $this->generateAll($ogService, $force);
    }

    protected function generateSingle(ServiceOgImageService $ogService, string $service, bool $force): int
    {
        if (! $ogService->isValidService($service)) {
            $this->error("Invalid service: {$service}");
            $this->line('Valid services: '.implode(', ', array_keys($ogService->getServices())));

            return self::FAILURE;
        }

        if (! $force && $ogService->exists($service)) {
            $this->info("Image already exists for {$service}. Use --force to regenerate.");

            return self::SUCCESS;
        }

        $this->info("Generating OG image for {$service}...");

        $url = $ogService->generate($service);

        if ($url) {
            $this->info("Generated: {$url}");

            return self::SUCCESS;
        }

        $this->error("Failed to generate image for {$service}");

        return self::FAILURE;
    }

    protected function generateAll(ServiceOgImageService $ogService, bool $force): int
    {
        $services = $ogService->getServices();

        $this->info('Generating OG images for '.count($services).' services...');
        $this->newLine();

        $bar = $this->output->createProgressBar(count($services));
        $bar->start();

        $results = [];

        foreach (array_keys($services) as $service) {
            if (! $force && $ogService->exists($service)) {
                $results[$service] = 'skipped (exists)';
                $bar->advance();

                continue;
            }

            $url = $ogService->generate($service);
            $results[$service] = $url ? 'generated' : 'failed';
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display results
        $this->table(
            ['Service', 'Status'],
            collect($results)->map(fn ($status, $service) => [$service, $status])->toArray()
        );

        $failed = collect($results)->filter(fn ($s) => $s === 'failed')->count();

        if ($failed > 0) {
            $this->error("{$failed} image(s) failed to generate.");

            return self::FAILURE;
        }

        $this->info('All OG images generated successfully.');

        return self::SUCCESS;
    }
}
