<div>
    {{-- Hero Section --}}
    <section class="relative overflow-hidden">
        <div class="relative max-w-7xl mx-auto px-6 md:px-10 xl:px-8 py-20 lg:py-32">
            <div class="max-w-3xl">
                {{-- Badge --}}
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm mb-6" style="background-color: color-mix(in srgb, var(--service-accent) 15%, transparent); border: 1px solid color-mix(in srgb, var(--service-accent) 30%, transparent); color: var(--service-accent);">
                    <i class="fa-solid fa-{{ $workspace['icon'] ?? 'cube' }}"></i>
                    {{ $workspace['name'] ?? 'Service' }}
                </div>

                {{-- Headline --}}
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
                    {{ $workspace['description'] ?? 'A powerful service' }}
                    <span style="color: var(--service-accent);">built for creators</span>
                </h1>

                {{-- Description --}}
                <p class="text-lg text-slate-400 mb-8 max-w-xl">
                    {{ config('core.app.tagline', 'Simple, powerful tools that help you build your online presence.') }}
                </p>

                {{-- CTAs --}}
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="{{ config('app.url') }}/register" class="inline-flex items-center justify-center px-8 py-3 font-semibold rounded-xl transition" style="background-color: var(--service-accent); color: var(--service-bg); box-shadow: 0 0 20px color-mix(in srgb, var(--service-accent) 30%, transparent);">
                        Start free trial
                    </a>
                    <a href="/features" class="inline-flex items-center justify-center px-8 py-3 font-semibold rounded-xl transition" style="background-color: color-mix(in srgb, var(--service-accent) 15%, transparent); color: var(--service-accent); border: 1px solid color-mix(in srgb, var(--service-accent) 25%, transparent);">
                        See all features
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Features Section --}}
    <section class="py-20 lg:py-28 bg-slate-900/50">
        <div class="max-w-7xl mx-auto px-6 md:px-10 xl:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">
                    Everything you need
                </h2>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                    Powerful tools built for creators and businesses.
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($features as $feature)
                    <div class="rounded-xl p-6 transition bg-slate-800/50" style="border: 1px solid color-mix(in srgb, var(--service-accent) 15%, transparent);">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4" style="background-color: color-mix(in srgb, var(--service-accent) 15%, transparent);">
                            <i class="fa-solid fa-{{ $feature['icon'] }} text-lg" style="color: var(--service-accent);"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">{{ $feature['title'] }}</h3>
                        <p class="text-sm text-slate-400">{{ $feature['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA Section --}}
    <section class="py-20" style="background: linear-gradient(135deg, var(--service-accent), color-mix(in srgb, var(--service-accent) 70%, black));">
        <div class="max-w-4xl mx-auto px-6 md:px-10 xl:px-8 text-center">
            <h2 class="text-3xl sm:text-4xl font-bold mb-4 text-slate-900">
                Ready to get started?
            </h2>
            <p class="text-lg mb-8 text-slate-800">
                {{ config('core.app.cta_text', 'Join thousands of users building with our platform.') }}
            </p>
            <a href="{{ config('app.url') }}/register" class="inline-flex items-center justify-center px-8 py-3 font-semibold rounded-xl transition shadow-lg bg-slate-900" style="color: var(--service-accent);">
                Get started free
            </a>
        </div>
    </section>
</div>
