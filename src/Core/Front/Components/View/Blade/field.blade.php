@props([
    'variant' => null,       // inline, stacked
])

<flux:field {{ $attributes }}>
    {{ $slot }}
</flux:field>
