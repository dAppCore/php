@props([
    'href',
    'icon' => null,
    'active' => false,
    'color' => 'gray',
    'badge' => null,
])

<li class="pl-4 pr-3 py-2 rounded-lg mb-0.5 last:mb-0 bg-linear-to-r @if($active) from-violet-500/[0.12] dark:from-violet-500/[0.24] to-violet-500/[0.04] @endif" x-data>
    <a class="block text-gray-800 dark:text-gray-100 truncate transition @if(!$active) hover:text-gray-900 dark:hover:text-white @endif"
       href="{{ $href }}"
       @click="if (window.innerWidth >= 640 && window.innerWidth < 1024 && !sidebarOpen) { $event.preventDefault(); $dispatch('open-sidebar'); }">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                @if($icon)
                <core:icon :name="$icon" class="shrink-0 {{ $active ? 'text-violet-500' : 'text-' . $color . '-500' }}" />
                @endif
                <span class="text-sm font-medium @if($icon) ml-4 @endif duration-200"
                      :class="{ 'sm:opacity-0 lg:opacity-100': !sidebarOpen, 'opacity-100': sidebarOpen }">{{ $slot }}</span>
            </div>
            @if($badge)
            <span class="text-xs bg-amber-500 text-white px-1.5 py-0.5 rounded-full duration-200"
                  :class="{ 'sm:opacity-0 lg:opacity-100': !sidebarOpen, 'opacity-100': sidebarOpen }">{{ $badge }}</span>
            @endif
        </div>
    </a>
</li>
