@props([
    'wire:model' => null,
    'value' => null,
    'disabled' => false,
])

<flux:menu.radio {{ $attributes }}>
    {{ $slot }}
</flux:menu.radio>
