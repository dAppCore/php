@props([
    'heading' => null,
    'subheading' => null,
    'count' => null,
    'badge' => null,
])

<flux:kanban.column.header {{ $attributes }}>
    {{ $slot }}
</flux:kanban.column.header>
