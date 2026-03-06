@props([
    'label' => null,
])

<flux:date-picker.input {{ $attributes }}>
    {{ $slot }}
</flux:date-picker.input>
