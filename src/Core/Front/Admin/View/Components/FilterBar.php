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

class FilterBar extends Component
{
    public string $gridCols;

    public function __construct(
        public ?int $cols = null,
    ) {
        $this->gridCols = $this->resolveGridCols();
    }

    protected function resolveGridCols(): string
    {
        return match ($this->cols) {
            2 => 'sm:grid-cols-2',
            3 => 'sm:grid-cols-3',
            4 => 'sm:grid-cols-4',
            5 => 'sm:grid-cols-5',
            default => 'sm:grid-cols-4',
        };
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('admin::components.filter-bar');
    }
}
