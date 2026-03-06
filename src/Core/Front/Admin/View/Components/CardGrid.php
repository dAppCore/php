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

class CardGrid extends Component
{
    public string $colsClass;

    public function __construct(
        public array $cards = [],
        public int $cols = 3,
        public string $empty = 'No items found.',
        public string $emptyIcon = 'squares-2x2',
    ) {
        $this->colsClass = $this->resolveColsClass();
    }

    protected function resolveColsClass(): string
    {
        return match ($this->cols) {
            1 => 'grid-cols-1',
            2 => 'grid-cols-1 sm:grid-cols-2',
            3 => 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-3',
            4 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
            default => 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-3',
        };
    }

    public function progressColor(array $stat): string
    {
        if (isset($stat['progressColor'])) {
            return $stat['progressColor'];
        }

        $progress = $stat['progress'] ?? 0;

        return match (true) {
            $progress > 80 => 'red',
            $progress > 50 => 'yellow',
            default => 'green',
        };
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('admin::components.card-grid');
    }
}
