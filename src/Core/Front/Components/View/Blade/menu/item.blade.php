@props([
    'href' => null,          // URL for link items
    'icon' => null,          // Icon name (left side)
    'iconTrailing' => null,  // Icon name (right side)
    'iconVariant' => null,   // outline, solid, mini, micro
    'kbd' => null,           // Keyboard shortcut hint
    'suffix' => null,        // Suffix text
    'variant' => null,       // default, danger
    'disabled' => false,
    'keepOpen' => false,
    'wire:click' => null,
])

<flux:menu.item {{ $attributes }}>
    {{ $slot }}
</flux:menu.item>
