@props([
    'disabled' => false,
])

<flux:autocomplete.item {{ $attributes }}>
    {{ $slot }}
</flux:autocomplete.item>
