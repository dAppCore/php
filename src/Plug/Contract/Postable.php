<?php

declare(strict_types=1);

namespace Core\Plug\Contract;

use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * Contract for content publishing operations.
 */
interface Postable
{
    /**
     * Publish content to the platform.
     *
     * @param  string  $text  Post content
     * @param  Collection  $media  Media items (may be empty)
     * @param  array  $params  Platform-specific options
     */
    public function publish(string $text, Collection $media, array $params = []): Response;
}
