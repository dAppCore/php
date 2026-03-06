@props([
    'expanded' => false,     // Default expanded state
])

<flux:accordion.item {{ $attributes }}>
    {{ $slot }}
</flux:accordion.item>
