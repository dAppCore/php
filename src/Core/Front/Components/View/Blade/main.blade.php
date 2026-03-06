@props([
    'container' => false,    // Apply max-width container
])

<flux:main {{ $attributes }}>
    {{ $slot }}
</flux:main>
