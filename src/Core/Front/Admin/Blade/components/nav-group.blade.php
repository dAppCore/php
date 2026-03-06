@props([
    'title' => null,
])

<div>
    @if($title)
    <h3 class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold pl-3">
        <span class="text-center w-6" :class="{ 'hidden sm:block lg:hidden': !sidebarOpen, 'hidden': sidebarOpen }" aria-hidden="true">...</span>
        <span :class="{ 'sm:hidden lg:block': !sidebarOpen, 'block': sidebarOpen }">{{ $title }}</span>
    </h3>
    @endif
    <ul class="@if($title) mt-3 @endif">
        {{ $slot }}
    </ul>
</div>
