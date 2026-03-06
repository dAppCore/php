@props([
    'logo' => null,
    'logoRoute' => '/',
    'logoText' => 'Admin',
])

<div class="min-w-fit">
    <!-- Sidebar backdrop (mobile + tablet overlay) -->
    <div
        class="fixed inset-0 bg-gray-900/30 z-40 lg:hidden transition-opacity duration-200"
        :class="sidebarOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'"
        aria-hidden="true"
        x-cloak
    ></div>

    <!-- Sidebar -->
    <div
        id="sidebar"
        class="flex sm:flex! flex-col fixed z-40 left-0 top-0 h-[100dvh] overflow-y-scroll sm:overflow-y-auto no-scrollbar w-64 sm:w-20 lg:w-64 shrink-0 bg-white dark:bg-gray-800 p-4 transition-all duration-200 ease-in-out border-r border-gray-200 dark:border-gray-700/60"
        :class="{
            'max-sm:translate-x-0': sidebarOpen,
            'max-sm:-translate-x-64': !sidebarOpen,
            'sm:w-64 lg:w-64': sidebarOpen,
            'sm:w-20 lg:w-64': !sidebarOpen
        }"
        @click.outside="sidebarOpen = false"
        @keydown.escape.window="sidebarOpen = false"
    >

        <!-- Sidebar header -->
        <div class="flex justify-between mb-10 pr-3 sm:px-2">
            <!-- Close button (mobile + tablet overlay) -->
            <button class="lg:hidden text-gray-500 hover:text-gray-400" :class="{ 'sm:hidden': !sidebarOpen }" @click.stop="sidebarOpen = false" aria-controls="sidebar" :aria-expanded="sidebarOpen">
                <span class="sr-only">Close sidebar</span>
                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10.7 18.7l1.4-1.4L7.8 13H20v-2H7.8l4.3-4.3-1.4-1.4L4 12z" />
                </svg>
            </button>
            <!-- Logo -->
            <a class="block" href="{{ $logoRoute }}">
                <div class="flex items-center gap-2">
                    @if($logo)
                        <img src="{{ $logo }}" alt="{{ $logoText }}" class="w-8 h-8">
                    @endif
                    <span class="text-lg font-bold text-gray-800 dark:text-gray-100 duration-200"
                          :class="{ 'sm:opacity-0 lg:opacity-100': !sidebarOpen, 'opacity-100': sidebarOpen }">{{ $logoText }}</span>
                </div>
            </a>
        </div>

        <!-- Navigation content (provided by module) -->
        <div class="space-y-8">
            {{ $slot }}
        </div>

    </div>
</div>
