<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Webhook;

class WebhookReceived
{
    public function __construct(
        public readonly string $source,
        public readonly string $callId,
    ) {}
}
