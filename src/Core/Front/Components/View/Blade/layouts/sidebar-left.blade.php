@props([
    'title' => null,
    'description' => null,
])

<x-layouts::partials.base :title="$title">
    <!-- Page wrapper -->
    <div class="flex flex-col min-h-screen overflow-hidden supports-[overflow:clip]:overflow-clip overscroll-none">

        <!-- Background gradient shapes -->
        <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none" aria-hidden="true">
            <div class="absolute top-0 -left-1/4 w-1/2 h-1/2 bg-gradient-to-br from-purple-500/10 to-transparent rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 -right-1/4 w-1/2 h-1/2 bg-gradient-to-tl from-blue-500/10 to-transparent rounded-full blur-3xl"></div>
        </div>

        <x-layouts::partials.header />

        <!-- Main Content -->
        <main id="main-content" class="grow pt-16 md:pt-20">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 md:py-12">

                <!-- Page Header -->
                @if($title)
                    <header class="mb-8 md:mb-12">
                        <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">
                            {{ $title }}
                        </h1>
                        @if($description)
                            <p class="text-lg text-slate-400">
                                {{ $description }}
                            </p>
                        @endif
                    </header>
                @endif

                <!-- Two Column Layout -->
                <div class="flex flex-col md:flex-row gap-8 md:gap-12">

                    <!-- Sidebar Navigation -->
                    <aside class="md:w-64 md:shrink-0">
                        <nav class="md:sticky md:top-24">
                            <!-- Mobile: horizontal scrolling nav -->
                            <div class="md:hidden overflow-x-auto pb-4 -mx-4 px-4 scrollbar-hide">
                                <div class="flex gap-2 min-w-max">
                                    {{ $sidebar }}
                                </div>
                            </div>

                            <!-- Desktop: vertical nav -->
                            <div class="hidden md:block stellar-card p-4">
                                <div class="space-y-1">
                                    {{ $sidebar }}
                                </div>
                            </div>
                        </nav>
                    </aside>

                    <!-- Main Content Area -->
                    <section class="flex-1 min-w-0">
                        {{ $slot }}
                    </section>

                </div>
            </div>
        </main>

        <x-layouts::partials.footer />

    </div>
</x-layouts::partials.base>
