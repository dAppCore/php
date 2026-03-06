@props([
    'class' => null,         // Additional CSS classes
])

<flux:card {{ $attributes }}>
    {{ $slot }}
</flux:card>
