@props([
    'position' => null,      // top, right, bottom, left
    'align' => null,         // start, center, end
    'offset' => null,        // pixels
    'gap' => null,           // pixels
])

<flux:dropdown {{ $attributes }}>
    {{ $slot }}
</flux:dropdown>
