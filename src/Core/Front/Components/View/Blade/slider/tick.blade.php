@props([
    'value' => null,
])

<flux:slider.tick :value="$value" {{ $attributes }}>
    {{ $slot }}
</flux:slider.tick>
