<x-satellite.layout :workspace="$workspace" :meta="$meta">

    <!-- Hero Section -->
    <section class="py-16 md:py-24">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-16">
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6">
                    <span class="bg-gradient-to-b from-slate-100 to-slate-400 bg-clip-text text-transparent">
                        {{ $content['site']['name'] ?? $workspace->name }}
                    </span>
                </h1>
                @if(isset($content['site']['description']))
                    <p class="text-lg md:text-xl text-slate-400 max-w-2xl mx-auto">
                        {{ $content['site']['description'] }}
                    </p>
                @endif
            </div>

            <!-- Featured Posts -->
            @if(!empty($content['featured_posts']))
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($content['featured_posts'] as $post)
                        <article class="group">
                            <a href="/blog/{{ $post['slug'] }}" class="block">
                                <!-- Featured Image -->
                                @if(isset($post['_embedded']['wp:featuredmedia'][0]))
                                    <div class="aspect-video rounded-xl overflow-hidden mb-4 bg-slate-800">
                                        <img
                                            src="{{ $post['_embedded']['wp:featuredmedia'][0]['source_url'] }}"
                                            alt="{{ $post['title']['rendered'] ?? '' }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                        >
                                    </div>
                                @else
                                    <div class="aspect-video rounded-xl mb-4 bg-slate-800 flex items-center justify-center">
                                        <i class="fa-solid fa-image text-4xl text-slate-600"></i>
                                    </div>
                                @endif

                                <!-- Post Info -->
                                <div class="flex items-center gap-2 text-sm text-slate-500 mb-2">
                                    <time datetime="{{ $post['date'] }}">
                                        {{ \Carbon\Carbon::parse($post['date'])->format('M j, Y') }}
                                    </time>
                                </div>

                                <h2 class="font-semibold text-lg text-slate-200 group-hover:text-white transition mb-2">
                                    {!! $post['title']['rendered'] ?? 'Untitled' !!}
                                </h2>

                                @if(isset($post['excerpt']['rendered']))
                                    <p class="text-slate-400 text-sm line-clamp-2">
                                        {!! strip_tags($post['excerpt']['rendered']) !!}
                                    </p>
                                @endif
                            </a>
                        </article>
                    @endforeach
                </div>

                <div class="text-center mt-12">
                    <a href="/blog" class="inline-flex items-center gap-2 px-6 py-3 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg transition">
                        View All Posts
                        <i class="fa-solid fa-arrow-right text-sm"></i>
                    </a>
                </div>
            @else
                <div class="text-center py-16">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-slate-800 flex items-center justify-center">
                        <i class="fa-solid fa-pen-to-square text-2xl text-slate-500"></i>
                    </div>
                    <p class="text-slate-400">No posts yet. Check back soon!</p>
                </div>
            @endif
        </div>
    </section>

</x-satellite.layout>
