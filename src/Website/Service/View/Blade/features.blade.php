<div>
    {{-- Hero Section --}}
    <section class="relative overflow-hidden">
        <div class="relative max-w-7xl mx-auto px-6 md:px-10 xl:px-8 py-20 lg:py-28">
            <div class="text-center max-w-3xl mx-auto">
                {{-- Badge --}}
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm mb-6" style="background-color: color-mix(in srgb, var(--service-accent) 15%, transparent); border: 1px solid color-mix(in srgb, var(--service-accent) 30%, transparent); color: var(--service-accent);">
                    <i class="fa-solid fa-{{ $workspace['icon'] ?? 'cube' }}"></i>
                    {{ $workspace['name'] ?? 'Service' }} Features
                </div>

                {{-- Headline --}}
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
                    Everything you need to
                    <span style="color: var(--service-accent);">succeed</span>
                </h1>

                {{-- Description --}}
                <p class="text-lg text-slate-400 mb-8 max-w-xl mx-auto">
                    {{ $workspace['description'] ?? 'Powerful features built for creators and businesses.' }}
                </p>
            </div>
        </div>
    </section>

    {{-- Features Grid --}}
    <section class="py-20 lg:py-28 bg-slate-900/50">
        <div class="max-w-7xl mx-auto px-6 md:px-10 xl:px-8">
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($features as $feature)
                    <div class="rounded-2xl p-8 transition group" style="background-color: color-mix(in srgb, var(--service-accent) 5%, rgb(30 41 59)); border: 1px solid color-mix(in srgb, var(--service-accent) 15%, transparent);">
                        <div class="w-14 h-14 rounded-xl flex items-center justify-center mb-6 transition" style="background-color: color-mix(in srgb, var(--service-accent) 15%, transparent);">
                            <i class="fa-solid fa-{{ $feature['icon'] }} text-xl" style="color: var(--service-accent);"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-3">{{ $feature['title'] }}</h3>
                        <p class="text-slate-400 leading-relaxed">{{ $feature['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Integration Section --}}
    <section class="py-20 lg:py-28">
        <div class="max-w-4xl mx-auto px-6 md:px-10 xl:px-8 text-center">
            <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">
                Part of the Host UK ecosystem
            </h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                {{ $workspace['name'] ?? 'This service' }} works seamlessly with other Host UK services.
                One account, unified billing, integrated tools.
            </p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="https://{{ app()->environment('local') ? 'social.host.test' : 'social.host.uk.com' }}" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-sm hover:bg-slate-700 transition">
                    <i class="fa-solid fa-share-nodes mr-2"></i>SocialHost
                </a>
                <a href="https://{{ app()->environment('local') ? 'analytics.host.test' : 'analytics.host.uk.com' }}" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-sm hover:bg-slate-700 transition">
                    <i class="fa-solid fa-chart-line mr-2"></i>AnalyticsHost
                </a>
                <a href="https://{{ app()->environment('local') ? 'notify.host.test' : 'notify.host.uk.com' }}" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-sm hover:bg-slate-700 transition">
                    <i class="fa-solid fa-bell mr-2"></i>NotifyHost
                </a>
                <a href="https://{{ app()->environment('local') ? 'trust.host.test' : 'trust.host.uk.com' }}" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-sm hover:bg-slate-700 transition">
                    <i class="fa-solid fa-star mr-2"></i>TrustHost
                </a>
                <a href="https://{{ app()->environment('local') ? 'support.host.test' : 'support.host.uk.com' }}" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-sm hover:bg-slate-700 transition">
                    <i class="fa-solid fa-headset mr-2"></i>SupportHost
                </a>
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
                Start your free trial today. No credit card required.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ config('app.url') }}/register" class="inline-flex items-center justify-center px-8 py-3 font-semibold rounded-xl transition shadow-lg bg-slate-900" style="color: var(--service-accent);">
                    Get started free
                </a>
                <a href="/pricing" class="inline-flex items-center justify-center px-8 py-3 font-semibold rounded-xl transition bg-white/20 text-slate-900 border border-slate-900/20 hover:bg-white/30">
                    View pricing
                </a>
            </div>
        </div>
    </section>
</div>
