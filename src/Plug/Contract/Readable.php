<?php

declare(strict_types=1);

namespace Core\Plug\Contract;

use Core\Plug\Response;

/**
 * Contract for reading posts and content.
 */
interface Readable
{
    /**
     * Get a single post by ID.
     */
    public function get(string $id): Response;

    /**
     * Get multiple posts (timeline/feed).
     *
     * @param  array  $params  Pagination and filter options
     */
    public function list(array $params = []): Response;
}
