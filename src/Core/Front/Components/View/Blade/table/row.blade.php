@props([
    'wire:key' => null,
])

<flux:table.row {{ $attributes }}>
    {{ $slot }}
</flux:table.row>
