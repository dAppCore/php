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

class LinkGrid extends Component
{
    public string $gridCols;

    public function __construct(
        public int $cols = 4,
        public array $items = [],
    ) {
        $this->gridCols = $this->resolveGridCols();
    }

    protected function resolveGridCols(): string
    {
        return match ($this->cols) {
            2 => 'grid-cols-1 sm:grid-cols-2',
            3 => 'grid-cols-1 sm:grid-cols-3',
            4 => 'grid-cols-2 md:grid-cols-4',
            default => 'grid-cols-2 md:grid-cols-4',
        };
    }

    public function itemColor(array $item): string
    {
        return $item['color'] ?? 'violet';
    }

    public function itemIcon(array $item): string
    {
        return $item['icon'] ?? 'arrow-right';
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('admin::components.link-grid');
    }
}
