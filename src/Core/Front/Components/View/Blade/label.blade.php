@props([
    'for' => null,           // Associated input ID
])

<flux:label {{ $attributes }}>
    {{ $slot }}
</flux:label>
