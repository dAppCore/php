@props([
    'color' => null,         // zinc, red, orange, amber, yellow, lime, green, emerald, teal, cyan, sky, blue, indigo, violet, purple, fuchsia, pink, rose
    'size' => null,          // sm, base, lg
    'variant' => null,       // solid, outline, pill
    'inset' => null,         // top, bottom, left, right
    'icon' => null,          // Icon name (Font Awesome)
    'iconTrailing' => null,  // Icon name (right side, Font Awesome)
])

<flux:badge {{ $attributes->except(['icon', 'iconTrailing']) }}>
    @if($icon)
        <core:icon :name="$icon" class="size-3" />
    @endif
    {{ $slot }}
    @if($iconTrailing)
        <core:icon :name="$iconTrailing" class="size-3" />
    @endif
</flux:badge>
