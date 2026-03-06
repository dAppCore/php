@props([
    'heading' => null,
])

<flux:menu.submenu {{ $attributes }}>
    {{ $slot }}
</flux:menu.submenu>
