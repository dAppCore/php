<?php

declare(strict_types=1);

namespace Core\Actions;

/**
 * Interface for actions that want explicit contracts.
 *
 * Optional - most actions just use the Action trait.
 * Use this when you need to type-hint against an action interface.
 */
interface Actionable
{
    /**
     * Execute the action.
     */
    public function handle(mixed ...$args): mixed;
}
