<footer class="mt-auto">
    {{-- Footer gradient border --}}
    <div class="h-px w-full" style="background: linear-gradient(to right, transparent, color-mix(in srgb, var(--service-accent) 20%, transparent), transparent);"></div>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                @if(config('core.app.logo') && file_exists(public_path(config('core.app.logo'))))
                    <img src="/{{ config('core.app.logo') }}" alt="{{ config('core.app.name', 'Service') }}" class="w-6 h-6 opacity-50">
                @else
                    <div class="w-6 h-6 rounded flex items-center justify-center opacity-50" style="background-color: var(--service-accent);">
                        <i class="fa-solid fa-{{ $workspace['icon'] ?? 'cube' }} text-xs text-slate-900"></i>
                    </div>
                @endif
                <span class="text-sm text-slate-500">
                    &copy; {{ date('Y') }} {{ config('core.app.name', $workspace['name'] ?? 'Service') }}
                </span>
            </div>
            <div class="flex items-center gap-6 text-sm text-slate-500">
                @if(config('core.app.privacy_url'))
                    <a href="{{ config('core.app.privacy_url') }}" class="hover:text-slate-300 transition">Privacy</a>
                @endif
                @if(config('core.app.terms_url'))
                    <a href="{{ config('core.app.terms_url') }}" class="hover:text-slate-300 transition">Terms</a>
                @endif
                @if(config('core.app.powered_by'))
                    <a href="{{ config('core.app.powered_by_url', '#') }}" class="hover:text-slate-300 transition flex items-center gap-1">
                        <i class="fa-solid fa-bolt text-xs" style="color: var(--service-accent);"></i>
                        Powered by {{ config('core.app.powered_by') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</footer>
