@props([
    'name' => null,          // Form field name for validation error
])

<flux:error {{ $attributes }}>
    {{ $slot }}
</flux:error>
