@props([
    'class' => null,
])

<flux:navbar {{ $attributes }}>
    {{ $slot }}
</flux:navbar>
