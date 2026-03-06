@props([
    'title' => null,
    'description' => null,
    'author' => null,
    'date' => null,
    'category' => null,
    'image' => null,
    'backLink' => null,
    'backLabel' => 'Back',
])

<x-layouts::partials.base :title="$title">
    <!-- Page wrapper -->
    <div class="flex flex-col min-h-screen overflow-hidden supports-[overflow:clip]:overflow-clip overscroll-none">

        <!-- Background gradient shapes -->
        <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none" aria-hidden="true">
            <div class="absolute top-0 left-1/4 w-1/2 h-1/3 bg-gradient-to-b from-purple-500/8 to-transparent rounded-full blur-3xl"></div>
        </div>

        <x-layouts::partials.header />

        <!-- Main Content -->
        <main id="main-content" class="grow pt-16 md:pt-20">

            <!-- Article Header -->
            @if($title)
                <header class="relative py-12 md:py-20 {{ $image ? 'pb-0 md:pb-0' : '' }}">
                    <!-- Subtle gradient background -->
                    <div class="absolute inset-0 bg-gradient-to-b from-slate-800/50 to-transparent pointer-events-none -z-10" aria-hidden="true"></div>

                    <div class="max-w-3xl mx-auto px-4 sm:px-6">

                        @if($backLink)
                            <a href="{{ $backLink }}" class="inline-flex items-center text-sm text-purple-400 hover:text-purple-300 transition mb-6 group">
                                <i class="fa-solid fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i>
                                {{ $backLabel }}
                            </a>
                        @endif

                        @if($category)
                            <div class="mb-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                    {{ $category }}
                                </span>
                            </div>
                        @endif

                        <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-white mb-4">
                            {{ $title }}
                        </h1>

                        @if($description)
                            <p class="text-lg md:text-xl text-slate-300 mb-6">
                                {{ $description }}
                            </p>
                        @endif

                        @if($author || $date)
                            <div class="flex items-center gap-4 text-sm text-slate-400">
                                @if($author)
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-blue-500 flex items-center justify-center text-white text-xs font-bold">
                                            {{ substr($author, 0, 1) }}
                                        </div>
                                        <span>{{ $author }}</span>
                                    </div>
                                @endif
                                @if($author && $date)
                                    <span class="text-slate-600">·</span>
                                @endif
                                @if($date)
                                    <time datetime="{{ $date }}">{{ \Carbon\Carbon::parse($date)->format('j M Y') }}</time>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if($image)
                        <div class="max-w-4xl mx-auto px-4 sm:px-6 mt-8 md:mt-12">
                            <figure class="relative rounded-xl overflow-hidden aspect-[16/9]">
                                <img src="{{ $image }}" alt="{{ $title }}" class="w-full h-full object-cover">
                            </figure>
                        </div>
                    @endif
                </header>
            @endif

            <!-- Article Content -->
            <article class="py-8 md:py-12">
                <div class="max-w-3xl mx-auto px-4 sm:px-6">
                    <div class="prose prose-lg prose-invert prose-purple max-w-none
                                prose-headings:font-bold prose-headings:text-white
                                prose-p:text-slate-300 prose-p:leading-relaxed
                                prose-a:text-purple-400 prose-a:no-underline hover:prose-a:underline
                                prose-strong:text-white
                                prose-code:text-purple-300 prose-code:bg-slate-800 prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded
                                prose-pre:bg-slate-800/50 prose-pre:border prose-pre:border-slate-700
                                prose-blockquote:border-purple-500 prose-blockquote:bg-slate-800/30 prose-blockquote:py-1 prose-blockquote:not-italic
                                prose-img:rounded-xl
                                prose-hr:border-slate-700
                                prose-li:text-slate-300
                                prose-th:text-white prose-td:text-slate-300">
                        {{ $slot }}
                    </div>
                </div>
            </article>

            <!-- Optional: Related content, newsletter, etc. -->
            @isset($after)
                <section class="py-12 md:py-16 border-t border-slate-800">
                    <div class="max-w-3xl mx-auto px-4 sm:px-6">
                        {{ $after }}
                    </div>
                </section>
            @endisset

        </main>

        <x-layouts::partials.footer />

    </div>
</x-layouts::partials.base>
