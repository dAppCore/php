@props([
    'align' => null,         // start, center, end
    'variant' => null,       // default, strong
    'sticky' => false,
])

<flux:table.cell {{ $attributes }}>
    {{ $slot }}
</flux:table.cell>
