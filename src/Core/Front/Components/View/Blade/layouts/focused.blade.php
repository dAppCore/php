@props([
    'title' => null,
    'description' => null,
    'step' => null,
    'totalSteps' => null,
    'showProgress' => false,
])

<x-layouts::partials.base :title="$title">
    <!-- Page wrapper -->
    <div class="flex flex-col min-h-screen overflow-hidden supports-[overflow:clip]:overflow-clip overscroll-none">

        <x-layouts::partials.header :minimal="true" />

        <!-- Main Content -->
        <main id="main-content" class="grow pt-16 md:pt-20 flex flex-col">

            <!-- Progress Bar -->
            @if($showProgress && $step && $totalSteps)
                <div class="w-full bg-slate-800">
                    <div class="max-w-xl mx-auto px-4 sm:px-6 py-3">
                        <div class="flex items-center justify-between text-sm text-slate-400 mb-2">
                            <span>Step {{ $step }} of {{ $totalSteps }}</span>
                            <span>{{ round(($step / $totalSteps) * 100) }}% complete</span>
                        </div>
                        <div class="h-1 bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-purple-500 to-blue-500 transition-all duration-500"
                                 style="width: {{ ($step / $totalSteps) * 100 }}%"></div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex-1 flex flex-col justify-center py-8 md:py-12">
                <div class="max-w-xl mx-auto px-4 sm:px-6 w-full">

                    <!-- Header -->
                    @if($title)
                        <header class="text-center mb-8">
                            <h1 class="text-2xl md:text-3xl font-bold text-white mb-2">
                                {{ $title }}
                            </h1>
                            @if($description)
                                <p class="text-slate-400">
                                    {{ $description }}
                                </p>
                            @endif
                        </header>
                    @endif

                    <!-- Form/Content Card -->
                    <div class="stellar-card p-6 md:p-8">
                        {{ $slot }}
                    </div>

                    <!-- Optional: Helper text below card -->
                    @isset($helper)
                        <div class="mt-6 text-center text-sm text-slate-500">
                            {{ $helper }}
                        </div>
                    @endisset

                </div>
            </div>

        </main>

        <x-layouts::partials.footer :minimal="true" />

    </div>
</x-layouts::partials.base>
