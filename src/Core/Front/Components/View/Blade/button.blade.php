{{--
    Core Button - Wrapper around flux:button with Font Awesome icon support

    Props: variant (primary|filled|outline|ghost|danger|subtle), size (xs|sm|base|lg|xl),
           icon, iconTrailing, href, type, disabled, loading, square, inset
--}}
@props([
    'icon' => null,
    'iconTrailing' => null,
    'size' => 'base',
    'square' => null,
])

@php
    // Determine if this is an icon-only (square) button
    $isSquare = $square ?? $slot->isEmpty();

    // Icon sizes based on button size (matching Flux's sizing)
    $iconSize = match($size) {
        'xs' => 'size-3',
        'sm' => $isSquare ? 'size-4' : 'size-3.5',
        default => $isSquare ? 'size-5' : 'size-4',
    };
@endphp

<flux:button {{ $attributes->except(['icon', 'iconTrailing', 'icon:trailing'])->merge(['size' => $size, 'square' => $square]) }}>
    @if($icon)
        <core:icon :name="$icon" :class="$iconSize" />
    @endif
    {{ $slot }}
    @if($iconTrailing)
        <core:icon :name="$iconTrailing" :class="$iconSize" />
    @endif
</flux:button>
