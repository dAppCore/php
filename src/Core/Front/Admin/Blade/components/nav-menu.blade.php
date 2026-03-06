@props([
    'title',
    'icon' => null,
    'active' => false,
    'color' => 'gray',
    'expanded' => null,
])

@php
    $isExpanded = $expanded ?? $active;
@endphp

<li class="mb-0.5" x-data="{ expanded: {{ $isExpanded ? 'true' : 'false' }} }">
    <a class="block text-gray-800 dark:text-gray-100 truncate transition hover:text-gray-900 dark:hover:text-white pl-4 pr-3 py-2 rounded-lg @if($active) bg-linear-to-r from-violet-500/[0.12] dark:from-violet-500/[0.24] to-violet-500/[0.04] @endif"
       href="#"
       @click.prevent="if (window.innerWidth >= 640 && window.innerWidth < 1024 && !sidebarOpen) { $dispatch('open-sidebar'); } else { expanded = !expanded; }">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                @if($icon)
                <core:icon :name="$icon" class="shrink-0 {{ $active ? 'text-violet-500' : 'text-' . $color . '-500' }}" />
                @endif
                <span class="text-sm font-medium @if($icon) ml-4 @endif duration-200"
                      :class="{ 'sm:opacity-0 lg:opacity-100': !sidebarOpen, 'opacity-100': sidebarOpen }">{{ $title }}</span>
            </div>
            <div class="flex shrink-0 ml-2 duration-200"
                 :class="{ 'sm:opacity-0 lg:opacity-100': !sidebarOpen, 'opacity-100': sidebarOpen }">
                <svg class="w-3 h-3 shrink-0 fill-current text-gray-400 dark:text-gray-500 transition-transform duration-200" :class="{ 'rotate-180': expanded }" viewBox="0 0 12 12">
                    <path d="M5.9 11.4L.5 6l1.4-1.4 4 4 4-4L11.3 6z" />
                </svg>
            </div>
        </div>
    </a>
    <div :class="{ 'sm:hidden lg:block': !sidebarOpen, 'block': sidebarOpen }" x-show="expanded" x-cloak>
        <ul class="pl-10 mt-1 space-y-1">
            {{ $slot }}
        </ul>
    </div>
</li>
