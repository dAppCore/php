@props([
    'axis' => null,              // x, y
])

<flux:chart.axis {{ $attributes }}>
    {{ $slot }}
</flux:chart.axis>
