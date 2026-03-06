<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Support;

use Core\Media\Events\ConversionProgress;
use Illuminate\Support\Facades\Event;

/**
 * Progress reporter for media conversions.
 *
 * Provides a simple interface for reporting conversion progress
 * through both events and callbacks. Can be used by conversion
 * classes to report progress to consumers.
 *
 * ## Usage in Conversions
 *
 * ```php
 * class MyConversion extends MediaConversion
 * {
 *     public function handle(): ?MediaConversionData
 *     {
 *         $reporter = $this->getProgressReporter();
 *         $reporter->start();
 *
 *         // Processing loop
 *         foreach ($items as $index => $item) {
 *             $this->processItem($item);
 *             $reporter->progress(($index + 1) / count($items) * 100);
 *         }
 *
 *         $reporter->complete($outputPath);
 *         return MediaConversionData::conversion($this);
 *     }
 * }
 * ```
 *
 * ## With Callbacks
 *
 * ```php
 * $conversion = new MediaImageResizerConversion();
 * $conversion->onProgress(function (int $percent, string $message) {
 *     echo "Progress: {$percent}% - {$message}\n";
 * });
 * $conversion->execute();
 * ```
 */
class ConversionProgressReporter
{
    /**
     * The source file path being converted.
     */
    protected string $filepath;

    /**
     * The conversion engine name.
     */
    protected string $engine;

    /**
     * Whether events should be dispatched.
     */
    protected bool $dispatchEvents = true;

    /**
     * Progress callback function.
     *
     * @var callable|null
     */
    protected $callback = null;

    /**
     * Additional context data.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Current progress percentage.
     */
    protected int $currentPercent = 0;

    /**
     * Minimum percent change before reporting.
     */
    protected int $minDelta = 1;

    /**
     * Create a new progress reporter.
     *
     * @param  string  $filepath  Source file path
     * @param  string  $engine  Conversion engine name
     */
    public function __construct(string $filepath, string $engine)
    {
        $this->filepath = $filepath;
        $this->engine = $engine;
    }

    /**
     * Set whether to dispatch events.
     *
     * @param  bool  $dispatch  Whether to dispatch events
     * @return $this
     */
    public function withEvents(bool $dispatch = true): static
    {
        $this->dispatchEvents = $dispatch;

        return $this;
    }

    /**
     * Set the progress callback.
     *
     * Callback signature: fn(int $percent, string $stage, ?string $message)
     *
     * @param  callable  $callback  Progress callback
     * @return $this
     */
    public function onProgress(callable $callback): static
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * Set additional context data.
     *
     * @param  array<string, mixed>  $context  Context data
     * @return $this
     */
    public function withContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Add a single context value.
     *
     * @param  string  $key  Context key
     * @param  mixed  $value  Context value
     * @return $this
     */
    public function addContext(string $key, mixed $value): static
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * Set minimum percent change before reporting.
     *
     * @param  int  $delta  Minimum change (default 1)
     * @return $this
     */
    public function setMinDelta(int $delta): static
    {
        $this->minDelta = max(1, $delta);

        return $this;
    }

    /**
     * Report that the conversion has started.
     *
     * @param  string|null  $message  Optional message
     */
    public function start(?string $message = null): void
    {
        $this->currentPercent = 0;
        $event = ConversionProgress::started($this->filepath, $this->engine, $this->context);

        $this->dispatch($event);
        $this->invokeCallback(0, ConversionProgress::STAGE_STARTED, $message ?? 'Conversion started');
    }

    /**
     * Report progress.
     *
     * @param  int  $percent  Progress percentage (0-100)
     * @param  string|null  $message  Optional status message
     */
    public function progress(int $percent, ?string $message = null): void
    {
        $percent = min(100, max(0, $percent));

        // Only report if the change is significant enough
        if (abs($percent - $this->currentPercent) < $this->minDelta) {
            return;
        }

        $this->currentPercent = $percent;
        $event = ConversionProgress::processing($this->filepath, $this->engine, $percent, $message, $this->context);

        $this->dispatch($event);
        $this->invokeCallback($percent, ConversionProgress::STAGE_PROCESSING, $message);
    }

    /**
     * Report progress from item counts.
     *
     * @param  int  $current  Current item number
     * @param  int  $total  Total items
     * @param  string|null  $message  Optional status message
     */
    public function progressItems(int $current, int $total, ?string $message = null): void
    {
        if ($total <= 0) {
            return;
        }

        $percent = (int) (($current / $total) * 100);
        $this->progress($percent, $message ?? "Processing item {$current} of {$total}");
    }

    /**
     * Report progress with a ratio (0.0 to 1.0).
     *
     * @param  float  $ratio  Progress ratio (0.0 to 1.0)
     * @param  string|null  $message  Optional status message
     */
    public function progressRatio(float $ratio, ?string $message = null): void
    {
        $percent = (int) (min(1.0, max(0.0, $ratio)) * 100);
        $this->progress($percent, $message);
    }

    /**
     * Report that the conversion has completed.
     *
     * @param  string|null  $outputPath  Output file path
     * @param  string|null  $message  Optional message
     */
    public function complete(?string $outputPath = null, ?string $message = null): void
    {
        $this->currentPercent = 100;
        $event = ConversionProgress::completed($this->filepath, $this->engine, $outputPath, $this->context);

        $this->dispatch($event);
        $this->invokeCallback(100, ConversionProgress::STAGE_COMPLETED, $message ?? 'Conversion completed');
    }

    /**
     * Report that the conversion has failed.
     *
     * @param  string  $error  Error message
     * @param  \Throwable|null  $exception  Optional exception
     */
    public function fail(string $error, ?\Throwable $exception = null): void
    {
        $context = $this->context;
        if ($exception !== null) {
            $context['exception_class'] = get_class($exception);
            $context['exception_trace'] = $exception->getTraceAsString();
        }

        $event = ConversionProgress::failed($this->filepath, $this->engine, $error, $context);

        $this->dispatch($event);
        $this->invokeCallback($this->currentPercent, ConversionProgress::STAGE_FAILED, $error);
    }

    /**
     * Dispatch the event if events are enabled.
     *
     * @param  ConversionProgress  $event  Event instance
     */
    protected function dispatch(ConversionProgress $event): void
    {
        if ($this->dispatchEvents) {
            Event::dispatch($event);
        }
    }

    /**
     * Invoke the callback if set.
     *
     * @param  int  $percent  Progress percentage
     * @param  string  $stage  Progress stage
     * @param  string|null  $message  Status message
     */
    protected function invokeCallback(int $percent, string $stage, ?string $message): void
    {
        if ($this->callback !== null) {
            call_user_func($this->callback, $percent, $stage, $message);
        }
    }

    /**
     * Get the current progress percentage.
     */
    public function getCurrentPercent(): int
    {
        return $this->currentPercent;
    }

    /**
     * Get the file path.
     */
    public function getFilepath(): string
    {
        return $this->filepath;
    }

    /**
     * Get the engine name.
     */
    public function getEngine(): string
    {
        return $this->engine;
    }
}
