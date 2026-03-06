<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Conversions;

use Core\Media\Abstracts\MediaConversion;
use Core\Media\Support\ImageResizer;
use Core\Media\Support\MediaConversionData;

/**
 * Image resizing media conversion.
 *
 * Resizes images to specified dimensions while maintaining aspect ratio
 * and preventing upscaling. Does not process GIF images to preserve animation.
 */
class MediaImageResizerConversion extends MediaConversion
{
    protected ?float $width = null;

    protected ?float $height = null;

    /**
     * Get the engine name for this conversion.
     */
    public function getEngineName(): string
    {
        return 'ImageResizer';
    }

    /**
     * Check if this conversion can be performed.
     *
     * Only processes static images (excludes GIFs to preserve animation).
     */
    public function canPerform(): bool
    {
        return $this->isImage() && ! $this->isGifImage();
    }

    /**
     * Get the output file path.
     */
    public function getPath(): string
    {
        return $this->getFilePathWithSuffix();
    }

    /**
     * Set the target width in pixels.
     */
    public function width(?float $value = null): static
    {
        $this->width = $value;

        return $this;
    }

    /**
     * Set the target height in pixels.
     */
    public function height(?float $value = null): static
    {
        $this->height = $value;

        return $this;
    }

    /**
     * Perform the image resize conversion.
     */
    public function handle(): ?MediaConversionData
    {
        $content = $this->filesystem($this->getFromDisk())->get($this->getFilepath());

        ImageResizer::make($content)
            ->disk($this->getToDisk())
            ->path($this->getPath())
            ->resize((int) $this->width, (int) $this->height);

        return MediaConversionData::conversion($this);
    }

    /**
     * Build options array for queue serialization.
     */
    protected function buildQueueOptions(): array
    {
        return array_merge(parent::buildQueueOptions(), [
            'width' => $this->width,
            'height' => $this->height,
        ]);
    }
}
