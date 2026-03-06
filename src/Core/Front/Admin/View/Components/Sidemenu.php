<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Admin\View\Components;

use Core\Front\Admin\AdminMenuRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class Sidemenu extends Component
{
    public array $items;

    /**
     * @param  array|null  $items  Legacy: pass items directly. If null, builds from registry.
     */
    public function __construct(?array $items = null)
    {
        if ($items !== null) {
            // Legacy mode: items passed directly
            $this->items = $items;
        } else {
            // Registry mode: build from registered providers
            $this->items = $this->buildFromRegistry();
        }
    }

    protected function buildFromRegistry(): array
    {
        $user = Auth::user();

        // Use current workspace from session, not default
        $workspace = null;
        if (class_exists(\Core\Tenant\Services\WorkspaceService::class)) {
            $workspace = app(\Core\Tenant\Services\WorkspaceService::class)->currentModel();
        }

        $isAdmin = false;
        if (class_exists(\Core\Tenant\Models\User::class) && $user instanceof \Core\Tenant\Models\User) {
            $isAdmin = $user->isHades();
        }

        return app(AdminMenuRegistry::class)->build($workspace, $isAdmin);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('admin::components.sidemenu');
    }
}
