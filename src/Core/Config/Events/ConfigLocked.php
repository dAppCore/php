<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\Events;

use Core\Config\Models\ConfigProfile;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a config value is locked (FINAL).
 *
 * Child scopes will inherit this value and cannot override.
 */
class ConfigLocked
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $keyCode,
        public readonly ConfigProfile $profile,
        public readonly ?int $channelId = null,
    ) {}
}
