@props([
    'heading' => null,
    'text' => null,
    'icon' => null,
    'inline' => false,
    'withProgress' => false,     // with-progress
])

<flux:file-upload.dropzone {{ $attributes }}>
    {{ $slot }}
</flux:file-upload.dropzone>
