<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Activity;

use Core\Activity\Console\ActivityPruneCommand;
use Core\Activity\Services\ActivityLogService;
use Core\Activity\View\Modal\Admin\ActivityFeed;
use Core\Events\AdminPanelBooting;
use Core\Events\ConsoleBooting;
use Livewire\Livewire;

/**
 * Activity module boot class.
 *
 * Registers activity logging features with the Core PHP framework:
 * - Console commands (activity:prune)
 * - Livewire components (ActivityFeed)
 * - Service bindings
 *
 * The module uses the spatie/laravel-activitylog package with
 * workspace-aware enhancements.
 */
class Boot
{
    public static array $listens = [
        ConsoleBooting::class => 'onConsole',
        AdminPanelBooting::class => 'onAdmin',
    ];

    /**
     * Register console commands.
     */
    public function onConsole(ConsoleBooting $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $event->command(ActivityPruneCommand::class);
    }

    /**
     * Register admin panel components and routes.
     */
    public function onAdmin(AdminPanelBooting $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Register view namespace
        $event->views('core.activity', __DIR__.'/View/Blade');

        // Register Livewire component (only if Livewire is available)
        if (app()->bound('livewire')) {
            Livewire::component('core.activity-feed', ActivityFeed::class);
        }

        // Bind service as singleton
        app()->singleton(ActivityLogService::class);
    }

    /**
     * Check if activity logging is enabled.
     */
    protected function isEnabled(): bool
    {
        return config('core.activity.enabled', true);
    }
}
