<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Config\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when config cache is invalidated.
 *
 * This event is dispatched when the config cache is manually cleared,
 * allowing modules to refresh their own caches that depend on config values.
 *
 * ## Invalidation Scope
 *
 * The event includes context about what was invalidated:
 * - `keyCode` - Specific key that was invalidated (null = all keys)
 * - `workspaceId` - Workspace scope (null = system scope)
 * - `channelId` - Channel scope (null = all channels)
 *
 * ## Listening to Invalidation
 *
 * ```php
 * use Core\Config\Events\ConfigInvalidated;
 *
 * class MyModuleListener
 * {
 *     public function handle(ConfigInvalidated $event): void
 *     {
 *         // Check if this affects our module's config
 *         if ($event->affectsKey('mymodule.api_key')) {
 *             // Clear our module's cached API client
 *             Cache::forget('mymodule:api_client');
 *         }
 *
 *         // Or handle full invalidation
 *         if ($event->isFull()) {
 *             // Clear all module caches
 *             Cache::tags(['mymodule'])->flush();
 *         }
 *     }
 * }
 * ```
 *
 * ## Invalidation Sources
 *
 * This event is fired by:
 * - `ConfigService::invalidateWorkspace()` - Clears workspace config
 * - `ConfigService::invalidateKey()` - Clears a specific key
 *
 * @see ConfigChanged For changes to specific config values
 * @see ConfigLocked For when config values are locked
 */
class ConfigInvalidated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly ?string $keyCode = null,
        public readonly ?int $workspaceId = null,
        public readonly ?int $channelId = null,
    ) {}

    /**
     * Is this a full invalidation?
     */
    public function isFull(): bool
    {
        return $this->keyCode === null && $this->workspaceId === null;
    }

    /**
     * Does this invalidation affect a specific key?
     */
    public function affectsKey(string $key): bool
    {
        if ($this->keyCode === null) {
            return true; // Full invalidation affects all keys
        }

        // Exact match or prefix match
        return $this->keyCode === $key || str_starts_with($key, $this->keyCode.'.');
    }
}
