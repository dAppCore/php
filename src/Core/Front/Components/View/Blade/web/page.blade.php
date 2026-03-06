<x-satellite.layout :workspace="$workspace" :meta="$meta">

    <article class="py-12 md:py-16">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">

            <!-- Header -->
            <header class="text-center mb-12">
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-slate-100 mb-4">
                    {!! $page['title']['rendered'] ?? 'Untitled' !!}
                </h1>
            </header>

            <!-- Featured Image -->
            @if(isset($page['_embedded']['wp:featuredmedia'][0]))
                <figure class="mb-10">
                    <img
                        src="{{ $page['_embedded']['wp:featuredmedia'][0]['source_url'] }}"
                        alt="{{ $page['title']['rendered'] ?? '' }}"
                        class="w-full rounded-xl"
                    >
                </figure>
            @endif

            <!-- Content -->
            <div class="prose prose-invert prose-slate max-w-none
                prose-headings:font-semibold prose-headings:text-slate-200
                prose-p:text-slate-300 prose-p:leading-relaxed
                prose-a:text-violet-400 prose-a:no-underline hover:prose-a:underline
                prose-strong:text-slate-200
                prose-blockquote:border-violet-500 prose-blockquote:text-slate-400
                prose-code:text-violet-300 prose-code:bg-slate-800 prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded
                prose-pre:bg-slate-800 prose-pre:border prose-pre:border-slate-700
                prose-img:rounded-xl
                prose-hr:border-slate-700
                prose-ul:text-slate-300 prose-ol:text-slate-300
                prose-li:text-slate-300
            ">
                {!! $page['content']['rendered'] ?? '' !!}
            </div>

        </div>
    </article>

</x-satellite.layout>
