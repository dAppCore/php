<header class="sticky top-0 z-30 bg-slate-900/90 backdrop-blur-xl">
    <div class="max-w-5xl mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between h-16">
            {{-- Branding --}}
            <a href="/" class="flex items-center gap-3 group">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: color-mix(in srgb, var(--service-accent) 20%, transparent);">
                    <i class="fa-solid fa-{{ $workspace['icon'] ?? 'cube' }}" style="color: var(--service-accent);"></i>
                </div>
                <span class="font-bold text-lg text-slate-200 group-hover:text-white transition">
                    {{ $workspace['name'] ?? 'Service' }}
                </span>
            </a>

            {{-- Navigation --}}
            <nav class="hidden sm:flex items-center gap-6">
                <a href="/" class="text-sm text-slate-400 hover:text-white transition">Home</a>
                <a href="/features" class="text-sm text-slate-400 hover:text-white transition">Features</a>
                <a href="/pricing" class="text-sm text-slate-400 hover:text-white transition">Pricing</a>
            </nav>

            {{-- Actions --}}
            <div class="flex items-center gap-4">
                <a href="{{ config('app.url') }}/login" class="text-sm text-slate-400 hover:text-white transition">Login</a>
                <a href="{{ config('app.url') }}/register" class="px-4 py-2 text-sm font-semibold rounded-lg transition" style="background-color: var(--service-accent); color: #1e293b;">
                    Get started
                </a>
            </div>
        </div>
    </div>
    {{-- Header gradient border --}}
    <div class="h-px w-full" style="background: linear-gradient(to right, transparent, color-mix(in srgb, var(--service-accent) 30%, transparent), transparent);"></div>
</header>
