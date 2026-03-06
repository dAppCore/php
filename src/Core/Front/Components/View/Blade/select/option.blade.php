@props([
    'value' => null,
    'disabled' => false,
])

<flux:select.option {{ $attributes }}>
    {{ $slot }}
</flux:select.option>
