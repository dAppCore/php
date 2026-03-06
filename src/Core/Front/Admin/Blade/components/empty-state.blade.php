@props([
    'message',
    'icon' => 'inbox',
])

<div {{ $attributes->merge(['class' => 'text-center py-8 text-gray-500 dark:text-gray-400']) }}>
    <core:icon :name="$icon" class="text-3xl mb-2" />
    <core:text>{{ $message }}</core:text>
</div>
