<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Webhook;

use Illuminate\Http\Request;

interface WebhookVerifier
{
    /**
     * Verify the webhook signature.
     *
     * Returns true if valid, false if invalid.
     */
    public function verify(Request $request, string $secret): bool;
}
