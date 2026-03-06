@props([
    'trailing' => false,     // Show below input instead of above
])

<flux:description {{ $attributes }}>
    {{ $slot }}
</flux:description>
