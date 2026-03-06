@props([
    'href' => null,          // Link URL
    'icon' => null,          // Icon name
    'badge' => null,         // Badge text/count
    'current' => false,      // Active state
    'wire:navigate' => null, // Livewire SPA navigation
])

<flux:navlist.item {{ $attributes }}>
    {{ $slot }}
</flux:navlist.item>
