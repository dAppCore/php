<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Cdn\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Job to push an asset from Hetzner S3 to CDN storage zone.
 *
 * This enables async replication from origin storage to CDN edge.
 */
class PushAssetToCdn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     *
     * @param  string  $disk  Laravel filesystem disk name (e.g., 'hetzner-public')
     * @param  string  $path  Path within the disk
     * @param  string  $zone  Target CDN zone ('public' or 'private')
     */
    public function __construct(
        public string $disk,
        public string $path,
        public string $zone = 'public',
    ) {
        $this->onQueue(config('cdn.pipeline.queue', 'cdn'));
    }

    /**
     * Execute the job.
     *
     * @param  object|null  $storage  StorageManager instance when Plug module available
     */
    public function handle(?object $storage = null): void
    {
        if (! class_exists(\Core\Plug\Storage\StorageManager::class)) {
            Log::warning('PushAssetToCdn: StorageManager not available, Plug module not installed');

            return;
        }

        // Resolve from container if not injected
        if ($storage === null) {
            $storage = app(\Core\Plug\Storage\StorageManager::class);
        }

        if (! config('cdn.bunny.push_enabled', false)) {
            Log::debug('PushAssetToCdn: Push disabled, skipping', [
                'disk' => $this->disk,
                'path' => $this->path,
            ]);

            return;
        }

        $uploader = $storage->zone($this->zone)->upload();

        if (! $uploader->isConfigured()) {
            Log::warning('PushAssetToCdn: CDN storage not configured', [
                'zone' => $this->zone,
            ]);

            return;
        }

        // Get contents from origin disk
        $sourceDisk = Storage::disk($this->disk);
        if (! $sourceDisk->exists($this->path)) {
            Log::warning('PushAssetToCdn: Source file not found on disk', [
                'disk' => $this->disk,
                'path' => $this->path,
            ]);

            return;
        }

        $contents = $sourceDisk->get($this->path);
        $result = $uploader->contents($this->path, $contents);

        if ($result->hasError()) {
            Log::error('PushAssetToCdn: Failed to push asset', [
                'disk' => $this->disk,
                'path' => $this->path,
                'zone' => $this->zone,
                'error' => $result->message(),
            ]);

            $this->fail(new \Exception("Failed to push {$this->path} to CDN zone {$this->zone}"));
        }

        Log::info('PushAssetToCdn: Asset pushed successfully', [
            'disk' => $this->disk,
            'path' => $this->path,
            'zone' => $this->zone,
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'cdn',
            'push',
            "zone:{$this->zone}",
            "path:{$this->path}",
        ];
    }

    /**
     * Determine if the job should be unique.
     */
    public function uniqueId(): string
    {
        return "{$this->zone}:{$this->path}";
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueFor(): int
    {
        return 300; // 5 minutes
    }
}
