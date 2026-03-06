@props([
    'paginate' => null,      // Laravel paginator instance
    'containerClass' => null, // CSS classes for container
])

<flux:table {{ $attributes }}>
    {{ $slot }}
</flux:table>
