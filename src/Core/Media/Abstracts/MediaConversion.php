<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Abstracts;

use Core\Media\Events\ConversionProgress;
use Core\Media\Jobs\ProcessMediaConversion;
use Core\Media\Support\ConversionProgressReporter;
use Core\Media\Support\MediaConversionData;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Abstract base class for media conversions.
 *
 * Provides common functionality for media processing operations
 * such as image resizing, thumbnail generation, and video processing.
 *
 * Supports queueing for large files to prevent request timeouts.
 * Configure via 'media.queue_threshold_mb' (default: 5MB).
 *
 * ## Progress Reporting
 *
 * Conversions can report progress through callbacks and/or events:
 *
 * ```php
 * $conversion = new MediaImageResizerConversion();
 * $conversion->filepath('image.jpg')
 *     ->onProgress(function (int $percent, string $stage, ?string $message) {
 *         echo "Progress: {$percent}%\n";
 *     })
 *     ->execute();
 * ```
 *
 * Or listen for events:
 *
 * ```php
 * Event::listen(ConversionProgress::class, function ($event) {
 *     Log::info("Conversion {$event->stage}: {$event->percent}%");
 * });
 * ```
 *
 * @see ConversionProgress For progress event details
 * @see ConversionProgressReporter For progress reporting implementation
 */
abstract class MediaConversion
{
    protected string $filepath;

    protected string $fromDisk = 'local';

    protected string $toDisk = 'local';

    protected string $name = '';

    protected string $suffix = '';

    /**
     * Whether to force synchronous processing (bypass queue).
     */
    protected bool $forceSync = false;

    /**
     * Additional options for conversion-specific configuration.
     *
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * Progress callback for reporting conversion progress.
     *
     * @var callable|null
     */
    protected $progressCallback = null;

    /**
     * Whether to dispatch progress events.
     */
    protected bool $dispatchProgressEvents = true;

    /**
     * Progress reporter instance.
     */
    protected ?ConversionProgressReporter $progressReporter = null;

    /**
     * Image MIME types.
     */
    protected const IMAGE_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'image/svg+xml',
    ];

    /**
     * Video MIME types.
     */
    protected const VIDEO_MIMES = [
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-ms-wmv',
        'video/webm',
        'video/ogg',
        'video/3gpp',
    ];

    /**
     * Get the engine name for this conversion.
     */
    abstract public function getEngineName(): string;

    /**
     * Check if this conversion can be performed.
     */
    abstract public function canPerform(): bool;

    /**
     * Get the output file path.
     */
    abstract public function getPath(): string;

    /**
     * Perform the conversion.
     */
    abstract public function handle(): ?MediaConversionData;

    /**
     * Set the source file path.
     */
    public function filepath(string $filepath): static
    {
        $this->filepath = $filepath;

        return $this;
    }

    /**
     * Set the source disk.
     */
    public function fromDisk(string $disk): static
    {
        $this->fromDisk = $disk;

        return $this;
    }

    /**
     * Set the destination disk.
     */
    public function toDisk(string $disk): static
    {
        $this->toDisk = $disk;

        return $this;
    }

    /**
     * Set the conversion name.
     */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the filename suffix.
     */
    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * Get the source file path.
     */
    public function getFilepath(): string
    {
        return $this->filepath;
    }

    /**
     * Get the source disk.
     */
    public function getFromDisk(): string
    {
        return $this->fromDisk;
    }

    /**
     * Get the destination disk.
     */
    public function getToDisk(): string
    {
        return $this->toDisk;
    }

    /**
     * Get the conversion name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the filename suffix.
     */
    public function getSuffix(): string
    {
        return $this->suffix;
    }

    /**
     * Force synchronous processing, bypassing the queue.
     */
    public function sync(): static
    {
        $this->forceSync = true;

        return $this;
    }

    /**
     * Set a conversion-specific option.
     */
    public function option(string $key, mixed $value): static
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Get the conversion options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set a progress callback.
     *
     * The callback receives three arguments:
     * - int $percent: Progress percentage (0-100)
     * - string $stage: Progress stage (started, processing, completed, failed)
     * - ?string $message: Optional status message
     *
     * @param  callable  $callback  Progress callback
     * @return $this
     */
    public function onProgress(callable $callback): static
    {
        $this->progressCallback = $callback;

        return $this;
    }

    /**
     * Enable or disable progress event dispatching.
     *
     * @param  bool  $dispatch  Whether to dispatch events
     * @return $this
     */
    public function withProgressEvents(bool $dispatch = true): static
    {
        $this->dispatchProgressEvents = $dispatch;

        return $this;
    }

    /**
     * Disable progress event dispatching.
     *
     * @return $this
     */
    public function withoutProgressEvents(): static
    {
        return $this->withProgressEvents(false);
    }

    /**
     * Get the progress reporter instance.
     *
     * Creates and configures a progress reporter for this conversion.
     * Call this in your handle() method to report progress.
     */
    protected function getProgressReporter(): ConversionProgressReporter
    {
        if ($this->progressReporter === null) {
            $this->progressReporter = new ConversionProgressReporter(
                $this->filepath,
                $this->getEngineName()
            );

            $this->progressReporter->withEvents($this->dispatchProgressEvents);

            if ($this->progressCallback !== null) {
                $this->progressReporter->onProgress($this->progressCallback);
            }

            $this->progressReporter->withContext([
                'fromDisk' => $this->fromDisk,
                'toDisk' => $this->toDisk,
                'name' => $this->name,
            ]);
        }

        return $this->progressReporter;
    }

    /**
     * Report progress (convenience method).
     *
     * Shorthand for getProgressReporter()->progress($percent, $message).
     *
     * @param  int  $percent  Progress percentage (0-100)
     * @param  string|null  $message  Optional status message
     */
    protected function reportProgress(int $percent, ?string $message = null): void
    {
        $this->getProgressReporter()->progress($percent, $message);
    }

    /**
     * Report progress from item counts (convenience method).
     *
     * @param  int  $current  Current item number
     * @param  int  $total  Total items
     * @param  string|null  $message  Optional status message
     */
    protected function reportProgressItems(int $current, int $total, ?string $message = null): void
    {
        $this->getProgressReporter()->progressItems($current, $total, $message);
    }

    /**
     * Execute the conversion, queueing if file exceeds threshold.
     *
     * @return MediaConversionData|null Returns null if queued
     */
    public function execute(): ?MediaConversionData
    {
        if (! $this->canPerform()) {
            return null;
        }

        // Check if we should queue this conversion
        if (! $this->forceSync && $this->shouldQueue()) {
            $this->dispatchToQueue();

            return null;
        }

        // Report start
        $reporter = $this->getProgressReporter();
        $reporter->start();

        try {
            $result = $this->handle();

            // Report completion
            $reporter->complete($result?->path);

            return $result;
        } catch (\Throwable $e) {
            // Report failure
            $reporter->fail($e->getMessage(), $e);

            throw $e;
        }
    }

    /**
     * Check if the file size exceeds the queue threshold.
     */
    protected function shouldQueue(): bool
    {
        $thresholdMb = config('media.queue_threshold_mb', 5);

        if ($thresholdMb <= 0) {
            return false;
        }

        $fileSize = $this->getFileSize();

        if ($fileSize === null) {
            return false;
        }

        $thresholdBytes = $thresholdMb * 1024 * 1024;

        return $fileSize > $thresholdBytes;
    }

    /**
     * Get the source file size in bytes.
     */
    protected function getFileSize(): ?int
    {
        $disk = $this->filesystem($this->fromDisk);

        if (! $disk->exists($this->filepath)) {
            return null;
        }

        return $disk->size($this->filepath);
    }

    /**
     * Dispatch the conversion to the queue.
     */
    protected function dispatchToQueue(): void
    {
        $config = [
            'filepath' => $this->filepath,
            'fromDisk' => $this->fromDisk,
            'toDisk' => $this->toDisk,
            'name' => $this->name,
            'suffix' => $this->suffix,
            'options' => $this->buildQueueOptions(),
        ];

        $queue = config('media.queue_name', 'default');

        ProcessMediaConversion::dispatch(static::class, $config)
            ->onQueue($queue);
    }

    /**
     * Build options array for queue serialization.
     *
     * Override in subclasses to include conversion-specific options.
     *
     * @return array<string, mixed>
     */
    protected function buildQueueOptions(): array
    {
        return $this->options;
    }

    /**
     * Get a filesystem instance for the given disk.
     */
    protected function filesystem(string $disk): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk($disk);
    }

    /**
     * Get the file path with a suffix added before the extension.
     */
    protected function getFilePathWithSuffix(?string $extension = null, ?string $basePath = null): string
    {
        $path = $basePath ?? $this->filepath;
        $directory = dirname($path);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $originalExtension = pathinfo($path, PATHINFO_EXTENSION);
        $ext = $extension ?? $originalExtension;

        $suffix = $this->suffix !== '' ? '-'.$this->suffix : '-'.Str::slug($this->name);

        if ($directory === '.') {
            return $filename.$suffix.'.'.$ext;
        }

        return $directory.'/'.$filename.$suffix.'.'.$ext;
    }

    /**
     * Check if the source file is an image.
     */
    protected function isImage(): bool
    {
        $mimeType = $this->getMimeType();

        return in_array($mimeType, self::IMAGE_MIMES, true);
    }

    /**
     * Check if the source file is a GIF image.
     */
    protected function isGifImage(): bool
    {
        return $this->getMimeType() === 'image/gif';
    }

    /**
     * Check if the source file is a video.
     */
    protected function isVideo(): bool
    {
        $mimeType = $this->getMimeType();

        return in_array($mimeType, self::VIDEO_MIMES, true);
    }

    /**
     * Get the MIME type of the source file.
     */
    protected function getMimeType(): ?string
    {
        $disk = $this->filesystem($this->fromDisk);

        if (! $disk->exists($this->filepath)) {
            return null;
        }

        return $disk->mimeType($this->filepath);
    }
}
