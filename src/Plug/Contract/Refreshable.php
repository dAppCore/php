<?php

declare(strict_types=1);

namespace Core\Plug\Contract;

use Core\Plug\Response;

/**
 * Contract for token refresh operations.
 */
interface Refreshable
{
    /**
     * Refresh an expired access token.
     *
     * @return Response Contains new token data on success
     */
    public function refresh(): Response;
}
