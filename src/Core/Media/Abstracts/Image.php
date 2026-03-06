<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Abstracts;

/**
 * Image dimension constants for media processing.
 *
 * Provides standard dimensions for thumbnails and resized images.
 */
abstract class Image
{
    /**
     * Small thumbnail dimensions.
     */
    public const SMALL_WIDTH = 150;

    public const SMALL_HEIGHT = 150;

    /**
     * Medium thumbnail dimensions.
     */
    public const MEDIUM_WIDTH = 400;

    public const MEDIUM_HEIGHT = 400;

    /**
     * Large image dimensions.
     */
    public const LARGE_WIDTH = 1200;

    public const LARGE_HEIGHT = 1200;

    /**
     * Maximum supported image dimensions.
     */
    public const MAX_WIDTH = 2400;

    public const MAX_HEIGHT = 2400;
}
