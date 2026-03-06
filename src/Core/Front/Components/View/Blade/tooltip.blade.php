@props([
    'content' => null,       // Tooltip text
    'position' => null,      // top, bottom, left, right
    'kbd' => null,           // Keyboard shortcut hint
])

<flux:tooltip {{ $attributes }}>
    {{ $slot }}
</flux:tooltip>
