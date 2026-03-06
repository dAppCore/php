<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

use Laravel\Horizon\Contracts\MasterSupervisorRepository;

/**
 * Laravel Horizon status checker.
 *
 * Monitors queue worker status via Horizon's supervisor repository.
 */
class HorizonStatus
{
    public function __construct(
        private readonly ?MasterSupervisorRepository $masterSupervisorRepository = null
    ) {}

    /**
     * Get current Horizon status.
     *
     * @return 'inactive'|'paused'|'active'
     */
    public function get(): string
    {
        if (! $masters = $this->masterSupervisorRepository?->all()) {
            return 'inactive';
        }

        if (collect($masters)->contains(function ($master) {
            return $master->status === 'paused';
        })) {
            return 'paused';
        }

        return 'active';
    }
}
