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

class Alert extends Component
{
    public string $bgColor;

    public string $iconName;

    public function __construct(
        public string $type = 'info',
        public ?string $title = null,
        public ?string $message = null,
        public ?string $icon = null,
        public ?array $action = null,
        public bool $dismissible = false,
    ) {
        $config = $this->resolveConfig();
        $this->bgColor = $config['bg'];
        $this->iconName = $this->icon ?? $config['icon'];
    }

    protected function resolveConfig(): array
    {
        return match ($this->type) {
            'warning' => ['bg' => 'amber', 'icon' => 'exclamation-triangle'],
            'success' => ['bg' => 'green', 'icon' => 'check-circle'],
            'error' => ['bg' => 'red', 'icon' => 'x-circle'],
            default => ['bg' => 'blue', 'icon' => 'information-circle'],
        };
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('admin::components.alert');
    }
}
