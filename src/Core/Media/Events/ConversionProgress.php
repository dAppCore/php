<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched during media conversion progress.
 *
 * This event is fired at various stages of a media conversion
 * process to report progress to listeners. It can be used for:
 *
 * - Displaying progress bars in UIs
 * - Logging conversion progress
 * - Tracking long-running conversions
 * - Broadcasting real-time updates via websockets
 *
 * ## Progress Stages
 *
 * - `started` - Conversion has begun
 * - `processing` - Conversion is in progress (with percentage)
 * - `completed` - Conversion finished successfully
 * - `failed` - Conversion encountered an error
 *
 * ## Usage
 *
 * Listen for this event to track conversion progress:
 *
 * ```php
 * use Core\Media\Events\ConversionProgress;
 *
 * Event::listen(ConversionProgress::class, function (ConversionProgress $event) {
 *     Log::info("Conversion {$event->stage}: {$event->percent}%", [
 *         'filepath' => $event->filepath,
 *         'engine' => $event->engine,
 *     ]);
 * });
 * ```
 */
class ConversionProgress
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Progress stage: Conversion started.
     */
    public const STAGE_STARTED = 'started';

    /**
     * Progress stage: Conversion in progress.
     */
    public const STAGE_PROCESSING = 'processing';

    /**
     * Progress stage: Conversion completed.
     */
    public const STAGE_COMPLETED = 'completed';

    /**
     * Progress stage: Conversion failed.
     */
    public const STAGE_FAILED = 'failed';

    /**
     * Create a new event instance.
     *
     * @param  string  $filepath  The source file path being converted
     * @param  string  $engine  The conversion engine name
     * @param  string  $stage  The current stage (started, processing, completed, failed)
     * @param  int  $percent  Progress percentage (0-100)
     * @param  string|null  $message  Optional progress message
     * @param  array<string, mixed>  $context  Additional context data
     */
    public function __construct(
        public readonly string $filepath,
        public readonly string $engine,
        public readonly string $stage,
        public readonly int $percent = 0,
        public readonly ?string $message = null,
        public readonly array $context = [],
    ) {}

    /**
     * Create a "started" progress event.
     *
     * @param  string  $filepath  Source file path
     * @param  string  $engine  Conversion engine name
     * @param  array<string, mixed>  $context  Additional context
     */
    public static function started(string $filepath, string $engine, array $context = []): static
    {
        return new static(
            filepath: $filepath,
            engine: $engine,
            stage: self::STAGE_STARTED,
            percent: 0,
            message: 'Conversion started',
            context: $context,
        );
    }

    /**
     * Create a "processing" progress event.
     *
     * @param  string  $filepath  Source file path
     * @param  string  $engine  Conversion engine name
     * @param  int  $percent  Progress percentage (0-100)
     * @param  string|null  $message  Optional status message
     * @param  array<string, mixed>  $context  Additional context
     */
    public static function processing(
        string $filepath,
        string $engine,
        int $percent,
        ?string $message = null,
        array $context = []
    ): static {
        return new static(
            filepath: $filepath,
            engine: $engine,
            stage: self::STAGE_PROCESSING,
            percent: min(100, max(0, $percent)),
            message: $message ?? "Processing: {$percent}%",
            context: $context,
        );
    }

    /**
     * Create a "completed" progress event.
     *
     * @param  string  $filepath  Source file path
     * @param  string  $engine  Conversion engine name
     * @param  string|null  $outputPath  Output file path
     * @param  array<string, mixed>  $context  Additional context
     */
    public static function completed(
        string $filepath,
        string $engine,
        ?string $outputPath = null,
        array $context = []
    ): static {
        $ctx = $context;
        if ($outputPath !== null) {
            $ctx['output_path'] = $outputPath;
        }

        return new static(
            filepath: $filepath,
            engine: $engine,
            stage: self::STAGE_COMPLETED,
            percent: 100,
            message: 'Conversion completed',
            context: $ctx,
        );
    }

    /**
     * Create a "failed" progress event.
     *
     * @param  string  $filepath  Source file path
     * @param  string  $engine  Conversion engine name
     * @param  string  $error  Error message
     * @param  array<string, mixed>  $context  Additional context
     */
    public static function failed(
        string $filepath,
        string $engine,
        string $error,
        array $context = []
    ): static {
        $ctx = array_merge($context, ['error' => $error]);

        return new static(
            filepath: $filepath,
            engine: $engine,
            stage: self::STAGE_FAILED,
            percent: 0,
            message: $error,
            context: $ctx,
        );
    }

    /**
     * Check if the conversion has started.
     */
    public function isStarted(): bool
    {
        return $this->stage === self::STAGE_STARTED;
    }

    /**
     * Check if the conversion is processing.
     */
    public function isProcessing(): bool
    {
        return $this->stage === self::STAGE_PROCESSING;
    }

    /**
     * Check if the conversion is completed.
     */
    public function isCompleted(): bool
    {
        return $this->stage === self::STAGE_COMPLETED;
    }

    /**
     * Check if the conversion has failed.
     */
    public function isFailed(): bool
    {
        return $this->stage === self::STAGE_FAILED;
    }

    /**
     * Check if this is a terminal state (completed or failed).
     */
    public function isTerminal(): bool
    {
        return $this->isCompleted() || $this->isFailed();
    }

    /**
     * Get the output path from context if available.
     */
    public function getOutputPath(): ?string
    {
        return $this->context['output_path'] ?? null;
    }

    /**
     * Get the error message from context if available.
     */
    public function getError(): ?string
    {
        return $this->context['error'] ?? null;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filepath' => $this->filepath,
            'engine' => $this->engine,
            'stage' => $this->stage,
            'percent' => $this->percent,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
