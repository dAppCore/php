@props([
    'href',
    'active' => false,
    'badge' => null,
    'icon' => null,
    'color' => null,
])

<li>
    <a class="flex items-center justify-between text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 truncate transition text-sm py-1 @if($active) !text-violet-500 @endif" href="{{ $href }}">
        <span class="flex items-center gap-2">
            @if($icon)
                <core:icon :name="$icon" class="size-4 shrink-0 {{ $color ? 'text-' . $color . '-500' : '' }}" />
            @endif
            {{ $slot }}
        </span>
        @if($badge)
        <span class="text-xs bg-amber-500 text-white px-1.5 py-0.5 rounded-full">{{ $badge }}</span>
        @endif
    </a>
</li>
