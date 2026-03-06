@props([
    'align' => null,         // start, center, end
    'sortable' => false,
    'sorted' => false,
    'direction' => null,     // asc, desc
    'sticky' => false,
])

<flux:table.column {{ $attributes }}>
    {{ $slot }}
</flux:table.column>
