@props([
    'format' => null,
])

<flux:chart.axis.tick {{ $attributes }}>
    {{ $slot }}
</flux:chart.axis.tick>
