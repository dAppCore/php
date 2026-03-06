<?php

declare(strict_types=1);

namespace Core\Plug\Enum;

/**
 * Response status for Plug operations.
 */
enum Status: string
{
    case OK = 'ok';
    case ERROR = 'error';
    case UNAUTHORIZED = 'unauthorized';
    case RATE_LIMITED = 'rate_limited';
    case NO_CONTENT = 'no_content';
}
