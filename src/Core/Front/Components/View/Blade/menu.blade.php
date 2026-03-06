@props([
    'keepOpen' => false,     // Keep menu open after selection
])

<flux:menu {{ $attributes }}>
    {{ $slot }}
</flux:menu>
