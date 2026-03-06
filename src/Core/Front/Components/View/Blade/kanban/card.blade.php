@props([
    'heading' => null,
    'as' => null,                // button, div
])

<flux:kanban.card {{ $attributes }}>
    {{ $slot }}
</flux:kanban.card>
