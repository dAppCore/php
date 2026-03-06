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
 * Fired when a config value is set or updated.
 *
 * This event is dispatched after `ConfigService::set()` is called,
 * providing both the new value and the previous value for comparison.
 *
 * ## Event Properties
 *
 * - `keyCode` - The config key that changed (e.g., 'cdn.bunny.api_key')
 * - `value` - The new value
 * - `previousValue` - The previous value (null if key was not set before)
 * - `profile` - The ConfigProfile where the value was set
 * - `channelId` - The channel ID (null if not channel-specific)
 *
 * ## Listening to Config Changes
 *
 * ```php
 * use Core\Config\Events\ConfigChanged;
 *
 * class MyModuleListener
 * {
 *     public function handle(ConfigChanged $event): void
 *     {
 *         if ($event->keyCode === 'cdn.bunny.api_key') {
 *             // API key changed - refresh CDN client
 *             $this->cdnService->refreshClient();
 *         }
 *
 *         // Check for prefix matches
 *         if (str_starts_with($event->keyCode, 'mymodule.')) {
 *             Cache::tags(['mymodule'])->flush();
 *         }
 *     }
 * }
 * ```
 *
 * ## In Module Boot.php
 *
 * ```php
 * use Core\Config\Events\ConfigChanged;
 *
 * class Boot
 * {
 *     public static array $listens = [
 *         ConfigChanged::class => 'onConfigChanged',
 *     ];
 *
 *     public function onConfigChanged(ConfigChanged $event): void
 *     {
 *         // Handle config changes
 *     }
 * }
 * ```
 *
 * @see ConfigInvalidated For cache invalidation events
 * @see ConfigLocked For when config values are locked
 */
class ConfigChanged
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $keyCode,
        public readonly mixed $value,
        public readonly mixed $previousValue,
        public readonly ConfigProfile $profile,
        public readonly ?int $channelId = null,
    ) {}
}
