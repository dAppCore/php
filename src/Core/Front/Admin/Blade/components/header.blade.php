@props([
    'sticky' => true,
])

<header
    class="@if($sticky) sticky top-0 @endif before:absolute before:inset-0 before:bg-white/40 before:dark:bg-gray-900/80 before:backdrop-blur-md before:-z-10 z-30 border-b border-gray-200/60 dark:border-gray-700/60"
>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 -mb-px">

            <!-- Left: Hamburger + slot -->
            <div class="flex items-center gap-4">
                <!-- Hamburger button (mobile) -->
                <button
                    class="text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 lg:hidden"
                    @click.stop="sidebarOpen = !sidebarOpen"
                    aria-controls="sidebar"
                    :aria-expanded="sidebarOpen"
                >
                    <span class="sr-only">Open sidebar</span>
                    <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="5" width="16" height="2" />
                        <rect x="4" y="11" width="16" height="2" />
                        <rect x="4" y="17" width="16" height="2" />
                    </svg>
                </button>

                {{ $left ?? '' }}
            </div>

            <!-- Right: Actions slot -->
            <div class="flex items-center gap-3">
                {{ $right ?? '' }}
                {{ $slot }}
            </div>

        </div>
    </div>
</header>
