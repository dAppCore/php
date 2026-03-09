<?php

declare(strict_types=1);

namespace Core\Plug\Contract;

use Core\Plug\Response;

/**
 * Contract for content deletion operations.
 */
interface Deletable
{
    /**
     * Delete a post by its external ID.
     */
    public function delete(string $id): Response;
}
