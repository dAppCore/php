<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Storage\Events;

/**
 * Dispatched when Redis becomes unavailable and fallback is activated.
 *
 * Listeners can use this event to trigger alerts, notifications,
 * or other monitoring actions when Redis fails.
 */
class RedisFallbackActivated
{
    public function __construct(
        public readonly string $context,
        public readonly string $errorMessage,
        public readonly string $fallbackDriver = 'database',
    ) {}
}
