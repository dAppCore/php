@props([
    'href',
    'title',
    'subtitle' => null,
    'icon' => 'arrow-right',
    'color' => 'violet',
    'wire' => true,
])

<a
    href="{{ $href }}"
    @if($wire) wire:navigate @endif
    {{ $attributes->merge(['class' => 'flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition']) }}
>
    <div class="w-8 h-8 rounded-full bg-{{ $color }}-100 dark:bg-{{ $color }}-900/30 flex items-center justify-center">
        <core:icon :name="$icon" class="text-{{ $color }}-600 dark:text-{{ $color }}-400 text-sm" />
    </div>
    <div class="flex-1 min-w-0">
        <div class="font-medium text-gray-800 dark:text-gray-100">{{ $title }}</div>
        @if($subtitle)
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $subtitle }}</div>
        @endif
    </div>
    @if($slot->isNotEmpty())
        {{ $slot }}
    @else
        <core:icon name="chevron-right" class="text-gray-400" />
    @endif
</a>
