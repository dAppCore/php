@props([
    'items' => null,
])

<flux:editor.toolbar {{ $attributes }}>
    {{ $slot }}
</flux:editor.toolbar>
