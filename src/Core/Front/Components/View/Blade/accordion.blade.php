@props([
    'transition' => null,    // Enable transition animations
    'exclusive' => null,     // Only one item open at a time
])

<flux:accordion {{ $attributes }}>
    {{ $slot }}
</flux:accordion>
