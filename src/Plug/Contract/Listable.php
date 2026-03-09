<?php

declare(strict_types=1);

namespace Core\Plug\Contract;

use Core\Plug\Response;

/**
 * Contract for listing entities (pages, boards, publications, etc.).
 *
 * Used by providers that require selecting a target entity before posting.
 * Examples: Meta pages, Pinterest boards, Medium publications.
 */
interface Listable
{
    /**
     * List available entities for the authenticated user.
     */
    public function listEntities(): Response;
}
