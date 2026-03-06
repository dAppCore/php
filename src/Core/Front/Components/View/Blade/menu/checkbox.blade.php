@props([
    'wire:model' => null,
    'value' => null,
    'disabled' => false,
])

<flux:menu.checkbox {{ $attributes }}>
    {{ $slot }}
</flux:menu.checkbox>
