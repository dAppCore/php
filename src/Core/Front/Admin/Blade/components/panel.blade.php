@props([
    'title' => null,
    'action' => null,
    'actionLabel' => 'View all',
])

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 rounded-lg shadow-sm']) }}>
    @if($title || $action)
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
            @if($title)
                <core:heading level="2" class="font-semibold text-gray-800 dark:text-gray-100">{{ $title }}</core:heading>
            @endif
            @if($action)
                <a href="{{ $action }}" wire:navigate class="text-sm text-violet-500 hover:text-violet-600">{{ $actionLabel }}</a>
            @endif
        </div>
    @endif
    <div class="p-5">
        {{ $slot }}
    </div>
</div>
