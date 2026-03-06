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

class DataTable extends Component
{
    public array $processedColumns;

    public function __construct(
        public ?string $title = null,
        public ?string $action = null,
        public string $actionLabel = 'View all',
        public array $columns = [],
        public array $rows = [],
        public string $empty = 'No data available',
        public string $emptyIcon = 'inbox',
    ) {
        $this->processedColumns = $this->processColumns();
    }

    protected function processColumns(): array
    {
        return array_map(fn ($col) => [
            'label' => is_array($col) ? ($col['label'] ?? $col) : $col,
            'align' => is_array($col) ? ($col['align'] ?? 'left') : 'left',
        ], $this->columns);
    }

    public function cellAlignClass(int $index): string
    {
        $colDef = $this->columns[$index] ?? [];
        $align = is_array($colDef) ? ($colDef['align'] ?? 'left') : 'left';

        return $align === 'right' ? 'text-right' : '';
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('admin::components.data-table');
    }
}
