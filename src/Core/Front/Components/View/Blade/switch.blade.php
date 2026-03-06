@props([
    'name' => null,          // Switch name attribute
    'label' => null,         // Label text
    'description' => null,   // Help text
    'disabled' => false,
    'wire:model' => null,    // Livewire binding
    'wire:model.live' => null,
])

<flux:switch {{ $attributes }} />
