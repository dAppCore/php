<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Input;

use Illuminate\Http\Request;

/**
 * Input capture - sanitise superglobals before Laravel boots.
 */
class Input
{
    public static function capture(): Request
    {
        $sanitiser = new Sanitiser;

        $_GET = $sanitiser->filter($_GET);
        $_POST = $sanitiser->filter($_POST);

        return Request::capture();
    }
}
