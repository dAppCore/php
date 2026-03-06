<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Admin\View\Components;

use Illuminate\View\Component;

class ServiceCard extends Component
{
    public string $name;

    public string $description;

    public string $icon;

    public string $color;

    public string $domain;

    public string $status;

    public string $statusColor;

    public array $stats;

    public array $actions;

    public ?string $adminRoute;

    public ?string $detailsRoute;

    public function __construct(array $service = [])
    {
        $this->name = $service['name'] ?? '';
        $this->description = $service['description'] ?? '';
        $this->icon = $service['icon'] ?? 'cube';
        $this->color = $service['color'] ?? 'violet';
        $this->domain = $service['domain'] ?? '';
        $this->status = $service['status'] ?? 'offline';
        $this->stats = $service['stats'] ?? [];
        $this->actions = $service['actions'] ?? [];
        $this->adminRoute = $service['adminRoute'] ?? null;
        $this->detailsRoute = $service['detailsRoute'] ?? null;
        $this->statusColor = $this->status === 'online' ? 'green' : 'red';
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('admin::components.service-card');
    }
}
