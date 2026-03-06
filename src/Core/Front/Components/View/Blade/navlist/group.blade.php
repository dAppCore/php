@props([
    'heading' => null,       // Group heading text
    'expandable' => false,   // Can be expanded/collapsed
    'expanded' => false,     // Initial expanded state
])

<flux:navlist.group {{ $attributes }}>
    {{ $slot }}
</flux:navlist.group>
