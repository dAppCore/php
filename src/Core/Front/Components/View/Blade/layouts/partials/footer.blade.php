@props([
    'minimal' => false,
])

@php
    $appName = config('core.app.name', __('core::core.brand.name'));
    $appLogo = config('core.app.logo', '/images/logo.svg');
    $appIcon = config('core.app.icon', '/images/icon.svg');
    $socialTwitter = config('core.social.twitter');
    $socialGithub = config('core.social.github');
@endphp

@if($minimal)
    <!-- Minimal Footer -->
    <footer class="border-t border-slate-800 mt-auto">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-slate-400">
                <div class="flex items-center gap-4">
                    <img src="{{ $appIcon }}" alt="{{ $appName }}" class="w-6 h-6 opacity-60">
                    <span>&copy; {{ date('Y') }} {{ $appName }}</span>
                </div>
                <div class="flex items-center gap-6">
                    <a href="/privacy" class="hover:text-white transition">{{ __('core::core.footer.privacy') }}</a>
                    <a href="/terms" class="hover:text-white transition">{{ __('core::core.footer.terms') }}</a>
                    <a href="/contact" class="hover:text-white transition">{{ __('core::core.nav.contact') }}</a>
                </div>
            </div>
        </div>
    </footer>
@else
    <!-- Full Footer -->
    <footer class="border-t border-slate-800 mt-auto">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-12">
            <!-- Footer Grid -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-8 mb-8">
                <!-- Company -->
                <div>
                    <h4 class="text-sm font-semibold text-white mb-4">{{ __('core::core.footer.company') }}</h4>
                    <ul class="space-y-2">
                        <li><a href="/about" class="text-sm text-slate-400 hover:text-white transition">{{ __('core::core.footer.about') }}</a></li>
                        <li><a href="/contact" class="text-sm text-slate-400 hover:text-white transition">{{ __('core::core.nav.contact') }}</a></li>
                        <li><a href="/pricing" class="text-sm text-slate-400 hover:text-white transition">{{ __('core::core.nav.pricing') }}</a></li>
                    </ul>
                </div>

                <!-- Marketing -->
                <div>
                    <h4 class="text-sm font-semibold text-white mb-4">Marketing</h4>
                    <ul class="space-y-2">
                        <li><a href="/services" class="text-sm text-slate-400 hover:text-white transition">Creator</a></li>
                        <li><a href="/services/seo" class="text-sm text-slate-400 hover:text-white transition">SEO</a></li>
                    </ul>
                </div>

                <!-- AI -->
                <div>
                    <h4 class="text-sm font-semibold text-white mb-4">AI</h4>
                    <ul class="space-y-2">
                        <li><a href="{{ route('ai') }}" class="text-sm text-slate-400 hover:text-white transition">AI Platform</a></li>
                        <li><a href="{{ route('ai.mcp') }}" class="text-sm text-slate-400 hover:text-white transition">MCP Servers</a></li>
                        <li><a href="{{ route('ai.ethics') }}" class="text-sm text-slate-400 hover:text-white transition">Ethics Framework</a></li>
                        <li><a href="{{ route('trees') }}" class="text-sm text-slate-400 hover:text-white transition">Trees for Agents</a></li>
                    </ul>
                </div>

                <!-- Support -->
                <div>
                    <h4 class="text-sm font-semibold text-white mb-4">{{ __('core::core.footer.support') }}</h4>
                    <ul class="space-y-2">
                        <li><a href="/help" class="text-sm text-slate-400 hover:text-white transition">{{ __('core::core.nav.help') }}</a></li>
                        <li><a href="/faq" class="text-sm text-slate-400 hover:text-white transition">{{ __('core::core.footer.faq') }}</a></li>
                        <li><a href="https://lthn.io" target="_blank" class="text-sm text-slate-400 hover:text-white transition">{{ __('core::core.footer.status') }}</a></li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h4 class="text-sm font-semibold text-white mb-4">{{ __('core::core.footer.legal') }}</h4>
                    <ul class="space-y-2">
                        <li><a href="/privacy" class="text-sm text-slate-400 hover:text-white transition">{{ __('core::core.footer.privacy') }}</a></li>
                        <li><a href="/terms" class="text-sm text-slate-400 hover:text-white transition">{{ __('core::core.footer.terms') }}</a></li>
                    </ul>
                </div>
            </div>

            <!-- Trust Badges & Payment -->
            <div class="border-t border-slate-800 pt-8 mb-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <!-- We Accept -->
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-slate-500 uppercase tracking-wider">We Accept</span>
                        <div class="flex items-center gap-2">
                            <i class="fa-brands fa-bitcoin text-xl text-orange-400" title="Bitcoin" aria-hidden="true"></i>
                            <i class="fa-solid fa-litecoin-sign text-xl text-slate-300" title="Litecoin" aria-hidden="true"></i>
                            <i class="fa-brands fa-monero text-xl text-orange-500" title="Monero" aria-hidden="true"></i>
                        </div>
                    </div>

                    <!-- SSL Seal -->
                    <a href="https://www.gogetssl.com" rel="nofollow" title="GoGetSSL Site Seal Logo">
                        <img src="https://gogetssl-cdn.s3.eu-central-1.amazonaws.com/site-seals/gogetssl-static-seal.svg" width="180" height="58" title="GoGetSSL Site Seal, Protected website" alt="GoGetSSL Site Seal"/>
                    </a>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="border-t border-slate-800 pt-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <img src="{{ $appIcon }}" alt="{{ $appName }}" class="w-8 h-8 opacity-60">
                    <div class="text-sm text-slate-400">
                        &copy; {{ date('Y') }} {{ $appName }}. {{ __('core::core.footer.all_rights') }}
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <x-fa-icon icon="shield-halved" class="text-green-500" /> {{ __('core::core.footer.gdpr') }}
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <x-fa-icon icon="server" class="text-purple-400" /> {{ __('core::core.footer.eu_hosted') }}
                    </div>
                    @if($socialTwitter || $socialGithub)
                        <div class="flex gap-4">
                            @if($socialTwitter)
                                <a class="text-slate-400 hover:text-purple-400 transition" href="{{ $socialTwitter }}" target="_blank" aria-label="Twitter">
                                    <i class="fa-brands fa-twitter" aria-hidden="true"></i>
                                </a>
                            @endif
                            @if($socialGithub)
                                <a class="text-slate-400 hover:text-purple-400 transition" href="{{ $socialGithub }}" target="_blank" aria-label="GitHub">
                                    <i class="fa-brands fa-github" aria-hidden="true"></i>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </footer>
@endif
