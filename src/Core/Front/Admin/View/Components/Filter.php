<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Front\Admin\View\Components;

use Illuminate\Support\Collection;
use Illuminate\View\Component;

class Filter extends Component
{
    public string $wireModel;

    public string $placeholderText;

    public array $normalizedOptions;

    public function __construct(
        public ?string $label = null,
        public ?string $placeholder = null,
        public array|Collection $options = [],
        public ?string $model = null,
        public string $valueKey = 'id',
        public string $labelKey = 'name',
    ) {
        $this->wireModel = $this->model ? "wire:model.live=\"{$this->model}\"" : '';
        $this->placeholderText = $this->placeholder ?? ($this->label ? "All {$this->label}s" : 'All');
        $this->normalizedOptions = $this->normalizeOptions();
    }

    protected function normalizeOptions(): array
    {
        return collect($this->options)->map(function ($item, $key) {
            if (is_object($item)) {
                return ['value' => $item->{$this->valueKey}, 'label' => $item->{$this->labelKey}];
            }
            if (is_array($item) && isset($item['value'])) {
                return $item;
            }

            return ['value' => $key, 'label' => $item];
        })->values()->all();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('admin::components.filter');
    }
}
