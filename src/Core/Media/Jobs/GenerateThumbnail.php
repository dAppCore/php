<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Jobs;

use Core\Media\Thumbnail\LazyThumbnail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Job for generating thumbnails asynchronously.
 *
 * Dispatched by LazyThumbnail when source images exceed the queue threshold.
 * Handles retries with exponential backoff and cleans up cache flags on completion.
 *
 * ## Configuration
 *
 * - Queue name: `images.lazy_thumbnails.queue_name` (default: 'default')
 * - Retry attempts: 3
 * - Timeout: 120 seconds
 */
class GenerateThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     *
     * @param  string  $sourcePath  Path to the source image
     * @param  int  $width  Target width in pixels
     * @param  int  $height  Target height in pixels
     * @param  array  $options  Additional options (source_disk, thumbnail_disk, prefix, quality)
     */
    public function __construct(
        public string $sourcePath,
        public int $width,
        public int $height,
        public array $options = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = $this->getCacheKey();

        Log::info('GenerateThumbnail: Starting thumbnail generation', [
            'source' => $this->sourcePath,
            'dimensions' => "{$this->width}x{$this->height}",
            'attempt' => $this->attempts(),
        ]);

        try {
            // Clear queued flag
            Cache::forget($cacheKey.':queued');

            // Create LazyThumbnail instance with options
            $lazyThumbnail = $this->createLazyThumbnail();

            // Check if source still exists and is processable
            if (! $lazyThumbnail->canGenerate($this->sourcePath)) {
                Log::warning('GenerateThumbnail: Source cannot be processed', [
                    'source' => $this->sourcePath,
                ]);

                return;
            }

            // Generate the thumbnail synchronously
            $result = $lazyThumbnail->generate($this->sourcePath, $this->width, $this->height);

            if ($result !== null) {
                Log::info('GenerateThumbnail: Thumbnail generated successfully', [
                    'source' => $this->sourcePath,
                    'thumbnail' => $result,
                    'dimensions' => "{$this->width}x{$this->height}",
                ]);
            } else {
                Log::warning('GenerateThumbnail: Thumbnail generation returned null', [
                    'source' => $this->sourcePath,
                    'dimensions' => "{$this->width}x{$this->height}",
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('GenerateThumbnail: Exception during generation', [
                'source' => $this->sourcePath,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        $cacheKey = $this->getCacheKey();

        // Clear all cache flags
        Cache::forget($cacheKey.':queued');
        Cache::forget($cacheKey.':generating');

        // Store failure flag briefly to prevent immediate re-queueing
        Cache::put($cacheKey.':failed', true, 300);

        Log::error('GenerateThumbnail: Job failed after all retries', [
            'source' => $this->sourcePath,
            'dimensions' => "{$this->width}x{$this->height}",
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Get the cache key for this thumbnail.
     */
    protected function getCacheKey(): string
    {
        $sourceDisk = $this->options['source_disk'] ?? 'public';

        return 'lazy_thumb:'.md5("{$sourceDisk}:{$this->sourcePath}:{$this->width}x{$this->height}");
    }

    /**
     * Create a LazyThumbnail instance with configured options.
     */
    protected function createLazyThumbnail(): LazyThumbnail
    {
        $lazyThumbnail = new LazyThumbnail(
            $this->options['source_disk'] ?? null,
            $this->options['thumbnail_disk'] ?? null
        );

        if (isset($this->options['prefix'])) {
            $lazyThumbnail->prefix($this->options['prefix']);
        }

        if (isset($this->options['quality'])) {
            $lazyThumbnail->quality($this->options['quality']);
        }

        return $lazyThumbnail;
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'media',
            'thumbnail',
            'source:'.$this->sourcePath,
        ];
    }
}
