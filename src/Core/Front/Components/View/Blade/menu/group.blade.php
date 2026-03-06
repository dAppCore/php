@props([
    'heading' => null,
])

<flux:menu.group {{ $attributes }}>
    {{ $slot }}
</flux:menu.group>
