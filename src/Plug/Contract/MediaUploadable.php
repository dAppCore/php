<?php

declare(strict_types=1);

namespace Core\Plug\Contract;

use Core\Plug\Response;

/**
 * Contract for media upload operations.
 */
interface MediaUploadable
{
    /**
     * Upload media to the platform.
     *
     * @param  array  $item  Media item with 'path', 'mime_type', 'name', etc.
     */
    public function upload(array $item): Response;
}
