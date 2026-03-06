<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Events;

/**
 * Fired when media processing is requested.
 *
 * Modules listen to this event to provide media handling capabilities
 * such as image processing, video transcoding, CDN integration, etc.
 * This enables lazy loading of heavy media processing dependencies.
 *
 * ## When This Event Fires
 *
 * Fired when the media system initializes, typically when media
 * upload or processing is triggered.
 *
 * ## Processor Types
 *
 * Register processors by type to handle different media formats:
 * - `image` - Image processing (resize, crop, optimize)
 * - `video` - Video transcoding and thumbnail generation
 * - `audio` - Audio processing and format conversion
 * - `document` - Document preview and text extraction
 *
 * ## Usage Example
 *
 * ```php
 * public static array $listens = [
 *     MediaRequested::class => 'onMedia',
 * ];
 *
 * public function onMedia(MediaRequested $event): void
 * {
 *     $event->processor('image', ImageProcessor::class);
 *     $event->processor('video', VideoProcessor::class);
 * }
 * ```
 */
class MediaRequested extends LifecycleEvent
{
    /** @var array<string, string> Collected processor registrations [type => class] */
    protected array $processorRequests = [];

    /**
     * Register a media processor for a specific type.
     *
     * @param  string  $type  Media type (e.g., 'image', 'video', 'audio')
     * @param  string  $class  Fully qualified processor class name
     */
    public function processor(string $type, string $class): void
    {
        $this->processorRequests[$type] = $class;
    }

    /**
     * Get all registered processors.
     *
     * @return array<string, string> [type => class]
     *
     * @internal Used by media system
     */
    public function processorRequests(): array
    {
        return $this->processorRequests;
    }
}
