@props([
    'level' => null,         // 1, 2, 3, 4, 5, 6 (renders h1-h6)
    'size' => null,          // xs, sm, base, lg, xl, 2xl
])

<flux:subheading {{ $attributes }}>
    {{ $slot }}
</flux:subheading>
