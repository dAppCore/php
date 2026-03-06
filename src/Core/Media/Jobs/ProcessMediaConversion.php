<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Jobs;

use Core\Media\Abstracts\MediaConversion;
use Core\Media\Events\ConversionProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Job for processing media conversions asynchronously.
 *
 * This job is dispatched when a media conversion exceeds the configured
 * file size threshold (default 5MB). Large files are processed in the
 * background to prevent request timeout issues.
 */
class ProcessMediaConversion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param  string  $conversionClass  The fully qualified class name of the conversion
     * @param  array  $conversionConfig  Configuration for the conversion (filepath, disk, etc.)
     */
    public function __construct(
        public string $conversionClass,
        public array $conversionConfig
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $filepath = $this->conversionConfig['filepath'] ?? 'unknown';

        Log::info('ProcessMediaConversion: Starting queued conversion', [
            'class' => $this->conversionClass,
            'filepath' => $filepath,
        ]);

        try {
            if (! class_exists($this->conversionClass)) {
                throw new \RuntimeException("Conversion class not found: {$this->conversionClass}");
            }

            /** @var MediaConversion $conversion */
            $conversion = new $this->conversionClass;

            // Apply configuration
            if (isset($this->conversionConfig['filepath'])) {
                $conversion->filepath($this->conversionConfig['filepath']);
            }

            if (isset($this->conversionConfig['fromDisk'])) {
                $conversion->fromDisk($this->conversionConfig['fromDisk']);
            }

            if (isset($this->conversionConfig['toDisk'])) {
                $conversion->toDisk($this->conversionConfig['toDisk']);
            }

            if (isset($this->conversionConfig['name'])) {
                $conversion->name($this->conversionConfig['name']);
            }

            if (isset($this->conversionConfig['suffix'])) {
                $conversion->suffix($this->conversionConfig['suffix']);
            }

            // Apply conversion-specific configuration
            foreach ($this->conversionConfig['options'] ?? [] as $method => $value) {
                if (method_exists($conversion, $method)) {
                    $conversion->{$method}($value);
                }
            }

            if (! $conversion->canPerform()) {
                Log::warning('ProcessMediaConversion: Conversion cannot be performed', [
                    'class' => $this->conversionClass,
                    'filepath' => $filepath,
                ]);

                return;
            }

            // Dispatch started event for queued job
            $engineName = $conversion->getEngineName();
            Event::dispatch(ConversionProgress::started($filepath, $engineName, [
                'queued' => true,
                'job_id' => $this->job?->getJobId(),
            ]));

            $result = $conversion->handle();

            // Dispatch completed event
            Event::dispatch(ConversionProgress::completed($filepath, $engineName, $result?->path, [
                'queued' => true,
                'job_id' => $this->job?->getJobId(),
            ]));

            Log::info('ProcessMediaConversion: Conversion completed', [
                'class' => $this->conversionClass,
                'filepath' => $filepath,
                'output_path' => $result?->path,
            ]);
        } catch (\Throwable $e) {
            // Dispatch failed event
            $engineName = $this->conversionClass::class ?? 'unknown';
            // Try to get engine name from the conversion class
            if (class_exists($this->conversionClass)) {
                try {
                    $tempConversion = new $this->conversionClass;
                    $engineName = $tempConversion->getEngineName();
                } catch (\Throwable) {
                    $engineName = class_basename($this->conversionClass);
                }
            }

            Event::dispatch(ConversionProgress::failed($filepath, $engineName, $e->getMessage(), [
                'queued' => true,
                'job_id' => $this->job?->getJobId(),
                'attempt' => $this->attempts(),
            ]));

            Log::error('ProcessMediaConversion: Conversion failed', [
                'class' => $this->conversionClass,
                'filepath' => $filepath,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }
}
