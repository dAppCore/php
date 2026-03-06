<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Admin\View\Components;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\View\Component;

class EditableTable extends Component
{
    public array $processedColumns;

    public function __construct(
        public array $columns = [],
        public array $rows = [],
        public ?Paginator $pagination = null,
        public string $empty = 'No items found.',
        public string $emptyIcon = 'inbox',
        public bool $selectable = false,
        public bool $selectAll = false,
        public string $selectModel = 'selected',
    ) {
        $this->processedColumns = $this->processColumns();
    }

    protected function processColumns(): array
    {
        return array_map(function ($column) {
            if (is_array($column)) {
                return [
                    'label' => $column['label'] ?? '',
                    'align' => $column['align'] ?? 'left',
                    'width' => $column['width'] ?? null,
                    'alignClass' => $this->alignClass($column['align'] ?? 'left'),
                ];
            }

            return [
                'label' => $column,
                'align' => 'left',
                'width' => null,
                'alignClass' => 'text-left',
            ];
        }, $this->columns);
    }

    public function cellAlignClass(int $cellIndex): string
    {
        $columnDef = $this->columns[$cellIndex] ?? [];
        $align = is_array($columnDef) ? ($columnDef['align'] ?? 'left') : 'left';

        return $this->alignClass($align);
    }

    protected function alignClass(string $align): string
    {
        return match ($align) {
            'center' => 'text-center',
            'right' => 'text-right',
            default => 'text-left',
        };
    }

    public function colspanCount(): int
    {
        return count($this->columns) + ($this->selectable ? 1 : 0);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('admin::components.editable-table');
    }
}
