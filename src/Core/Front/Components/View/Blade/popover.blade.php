@props([
    'position' => null,      // top, bottom, left, right
    'align' => null,         // start, center, end
    'offset' => null,        // Offset from trigger
    'trigger' => null,       // click, hover
])

<flux:popover {{ $attributes }}>
    {{ $slot }}
</flux:popover>
