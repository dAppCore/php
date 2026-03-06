@props([
    'href' => null,          // URL
    'current' => null,       // boolean, auto-detected if null
    'icon' => null,          // Icon name (left side)
    'iconTrailing' => null,  // Icon name (right side)
    'badge' => null,         // Badge text/slot
    'badgeColor' => null,    // Badge colour
    'badgeVariant' => null,  // solid, outline
    'wire:navigate' => null,
])

<flux:navbar.item {{ $attributes }}>
    {{ $slot }}
</flux:navbar.item>
