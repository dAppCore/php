<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Support;

use Core\Media\Abstracts\MediaConversion;

/**
 * Data transfer object for media conversion results.
 *
 * Encapsulates the result of a media conversion operation.
 */
class MediaConversionData
{
    public function __construct(
        public readonly string $path,
        public readonly string $disk,
        public readonly string $engine,
        public readonly string $name,
    ) {}

    /**
     * Create a conversion data instance from a MediaConversion.
     */
    public static function conversion(MediaConversion $conversion): static
    {
        return new static(
            path: $conversion->getPath(),
            disk: $conversion->getToDisk(),
            engine: $conversion->getEngineName(),
            name: $conversion->getName(),
        );
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'disk' => $this->disk,
            'engine' => $this->engine,
            'name' => $this->name,
        ];
    }
}
