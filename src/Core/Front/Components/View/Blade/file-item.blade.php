@props([
    'heading' => null,
    'text' => null,
    'image' => null,
    'size' => null,
    'icon' => null,
    'invalid' => false,
])

<flux:file-item {{ $attributes }}>
    {{ $slot }}
</flux:file-item>
