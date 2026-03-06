@props([
    'icon' => null,
    'kbd' => null,
])

<flux:command.item {{ $attributes }}>
    {{ $slot }}
</flux:command.item>
