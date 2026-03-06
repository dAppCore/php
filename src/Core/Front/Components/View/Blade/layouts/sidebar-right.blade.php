@props([
    'title' => null,
    'description' => null,
    'backLink' => null,
    'backLabel' => 'Back',
])

<x-layouts::partials.base :title="$title">
    <x-slot:head>
        <style>
            /* Scrollspy active state */
            .toc-link.active {
                color: rgb(192 132 252); /* purple-400 */
                border-left-color: rgb(192 132 252);
            }
        </style>
    </x-slot:head>

    <!-- Page wrapper -->
    <div class="flex flex-col min-h-screen overflow-hidden supports-[overflow:clip]:overflow-clip overscroll-none">

        <!-- Background gradient shapes -->
        <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none" aria-hidden="true">
            <div class="absolute top-0 left-1/4 w-1/2 h-1/3 bg-gradient-to-b from-purple-500/8 to-transparent rounded-full blur-3xl"></div>
        </div>

        <x-layouts::partials.header />

        <!-- Main Content -->
        <main class="grow pt-16 md:pt-20">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 md:py-12">

                <!-- Page Header -->
                @if($title)
                    <header class="mb-8 md:mb-12 max-w-3xl">
                        @if($backLink)
                            <a href="{{ $backLink }}" class="inline-flex items-center text-sm text-purple-400 hover:text-purple-300 transition mb-4 group">
                                <i class="fa-solid fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i>
                                {{ $backLabel }}
                            </a>
                        @endif

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
                <div class="flex gap-8 lg:gap-12">

                    <!-- Main Content Area -->
                    <article class="flex-1 min-w-0">
                        <div class="prose prose-lg prose-invert prose-purple max-w-none
                                    prose-headings:font-bold prose-headings:text-white prose-headings:scroll-mt-24
                                    prose-p:text-slate-300 prose-p:leading-relaxed
                                    prose-a:text-purple-400 prose-a:no-underline hover:prose-a:underline
                                    prose-strong:text-white
                                    prose-code:text-purple-300 prose-code:bg-slate-800 prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded
                                    prose-pre:bg-slate-800/50 prose-pre:border prose-pre:border-slate-700
                                    prose-blockquote:border-purple-500 prose-blockquote:bg-slate-800/30 prose-blockquote:py-1 prose-blockquote:not-italic
                                    prose-img:rounded-xl
                                    prose-hr:border-slate-700
                                    prose-li:text-slate-300">
                            {{ $slot }}
                        </div>
                    </article>

                    <!-- Table of Contents Sidebar -->
                    @isset($toc)
                        <aside class="hidden lg:block w-56 shrink-0">
                            <nav class="sticky top-24">
                                <div class="stellar-card p-4">
                                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">
                                        On this page
                                    </h4>
                                    <div class="space-y-1 text-sm">
                                        {{ $toc }}
                                    </div>
                                </div>
                            </nav>
                        </aside>
                    @endisset

                </div>
            </div>
        </main>

        <x-layouts::partials.footer />

    </div>

    <x-slot:scripts>
        <script>
            // Simple scrollspy for TOC
            document.addEventListener('DOMContentLoaded', function() {
                const tocLinks = document.querySelectorAll('.toc-link');
                const headings = document.querySelectorAll('h2[id], h3[id]');

                if (tocLinks.length === 0 || headings.length === 0) return;

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            tocLinks.forEach(link => link.classList.remove('active'));
                            const activeLink = document.querySelector(`.toc-link[href="#${entry.target.id}"]`);
                            if (activeLink) activeLink.classList.add('active');
                        }
                    });
                }, {
                    rootMargin: '-100px 0px -66%',
                    threshold: 0
                });

                headings.forEach(heading => observer.observe(heading));
            });
        </script>
    </x-slot:scripts>
</x-layouts::partials.base>
