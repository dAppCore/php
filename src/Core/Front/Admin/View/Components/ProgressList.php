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

class ProgressList extends Component
{
    public int|float $maxValue;

    public function __construct(
        public ?string $title = null,
        public ?string $action = null,
        public string $actionLabel = 'View All',
        public array $items = [],
        public string $color = 'violet',
        public string $empty = 'No data available',
        public string $emptyIcon = 'chart-bar',
    ) {
        $this->maxValue = $this->calculateMaxValue();
    }

    protected function calculateMaxValue(): int|float
    {
        return collect($this->items)->max(fn ($item) => $item['max'] ?? $item['value']) ?? 0;
    }

    public function itemPercentage(array $item): float
    {
        $value = $item['value'] ?? 0;
        $max = $item['max'] ?? $this->maxValue;

        return $max > 0 ? ($value / $max) * 100 : 0;
    }

    public function itemColor(array $item): string
    {
        return $item['color'] ?? $this->color;
    }

    public function formatValue(mixed $value): string
    {
        return is_numeric($value) ? number_format($value) : (string) $value;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('admin::components.progress-list');
    }
}
