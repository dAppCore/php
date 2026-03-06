@props([
    'field' => null,
])

<flux:chart.summary {{ $attributes }}>
    {{ $slot }}
</flux:chart.summary>
