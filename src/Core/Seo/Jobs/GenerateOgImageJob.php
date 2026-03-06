<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generate OG Image Job
 *
 * Queue-based generation of Open Graph images for pages.
 * Regenerates images when page settings change.
 */
class GenerateOgImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $pageId,
        public string $template = 'default',
        public bool $force = false
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     *
     * Requires Core\Mod\Web module to be installed for full functionality.
     */
    public function handle(): void
    {
        // Check if required Web module classes exist
        if (! class_exists(\Core\Mod\Web\Models\Page::class)) {
            Log::warning('OG image generation skipped: Web module not installed');

            return;
        }

        if (! class_exists(\Core\Mod\Web\Services\DynamicOgImageService::class)) {
            Log::warning('OG image generation skipped: DynamicOgImageService not available');

            return;
        }

        $ogService = app(\Core\Mod\Web\Services\DynamicOgImageService::class);
        $page = \Core\Mod\Web\Models\Page::find($this->pageId);

        if (! $page) {
            Log::warning("OG image generation skipped: page {$this->pageId} not found");

            return;
        }

        // Skip if disabled globally
        if (! config('bio.og_images.enabled', true)) {
            return;
        }

        // Skip if image exists and is not stale (unless forced)
        if (! $this->force && $ogService->exists($page) && ! $ogService->isStale($page)) {
            return;
        }

        try {
            $url = $ogService->generate($page, $this->template);

            Log::info("OG image generated for page {$page->id}", [
                'url' => $url,
                'template' => $this->template,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to generate OG image for page {$page->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['og-image', "page:{$this->pageId}"];
    }
}
