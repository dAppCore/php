@props([
    'icon' => null,
    'iconVariant' => null,       // icon-variant
    'tooltip' => null,
    'disabled' => false,
])

<flux:editor.button {{ $attributes }}>
    {{ $slot }}
</flux:editor.button>
