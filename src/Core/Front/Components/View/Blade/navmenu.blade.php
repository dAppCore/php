@props([
    'position' => null,      // top, right, bottom, left
    'align' => null,         // start, center, end
])

<flux:navmenu {{ $attributes }}>
    {{ $slot }}
</flux:navmenu>
