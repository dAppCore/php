@php
    $appName = config('core.app.name', 'Core PHP');
    $appIcon = config('core.app.icon', '/images/icon.svg');
    $appUrl = config('app.url', 'https://core.test');
    $privacyUrl = config('core.urls.privacy', '/privacy');
    $termsUrl = config('core.urls.terms', '/terms');

    // Footer settings - can be passed as array or FooterSettings object
    $footer = $footer ?? null;
    $footerShowDefault = $footer['show_default_links'] ?? $footer?->showDefaultLinks ?? true;
    $footerPosition = $footer['position'] ?? $footer?->position ?? 'above_default';
    $footerCustomContent = $footer['custom_content'] ?? $footer?->customContent ?? null;
    $footerCustomLinks = $footer['custom_links'] ?? $footer?->customLinks ?? [];
    $footerSocialLinks = $footer['social_links'] ?? $footer?->socialLinks ?? [];
    $footerCopyright = $footer['copyright_text'] ?? $footer?->copyrightText ?? null;
    $footerContactEmail = $footer['contact_email'] ?? $footer?->contactEmail ?? null;
    $footerContactPhone = $footer['contact_phone'] ?? $footer?->contactPhone ?? null;
    $footerHasCustom = $footerCustomContent || !empty($footerCustomLinks) || !empty($footerSocialLinks) || $footerContactEmail || $footerContactPhone;
@endphp
<!DOCTYPE html>
<html lang="en" class="scroll-smooth overscroll-none"
      x-data="{ darkMode: localStorage.getItem('dark-mode') === 'true' || (localStorage.getItem('dark-mode') === null && window.matchMedia('(prefers-color-scheme: dark)').matches) }"
      x-init="$watch('darkMode', val => localStorage.setItem('dark-mode', val))"
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $meta['title'] ?? $workspace?->name ?? $appName }}</title>
    <meta name="description" content="{{ $meta['description'] ?? '' }}">

    @if(isset($meta['image']))
    <meta property="og:image" content="{{ $meta['image'] }}">
    <meta name="twitter:image" content="{{ $meta['image'] }}">
    @endif

    <meta property="og:title" content="{{ $meta['title'] ?? $workspace?->name ?? $appName }}">
    <meta property="og:description" content="{{ $meta['description'] ?? '' }}">
    <meta property="og:url" content="{{ $meta['url'] ?? request()->url() }}">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">

    <!-- Fonts -->
    @include('layouts::partials.fonts')

    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css?v={{ filemtime(public_path('vendor/fontawesome/css/all.min.css')) }}">

    <!-- Tailwind / Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Prevent flash of wrong theme --}}
    <script>
        (function() {
            var darkMode = localStorage.getItem('dark-mode');
            if (darkMode === 'true' || (darkMode === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <style>
        /* Prevent flash of unstyled content */
        html { background-color: #0f172a; }
        html:not(.dark) { background-color: #f8fafc; }

        /* Swing animation for decorative shapes */
        @keyframes swing {
            0%, 100% { transform: rotate(-3deg); }
            50% { transform: rotate(3deg); }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body class="font-sans antialiased bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 tracking-tight min-h-screen flex flex-col overflow-x-hidden overscroll-none">

    <!-- Decorative background shapes -->
    <div class="fixed inset-0 -z-10 pointer-events-none overflow-hidden" aria-hidden="true">
        <div class="absolute w-[960px] h-24 top-12 left-1/2 -translate-x-1/2 animate-[swing_8s_ease-in-out_infinite] blur-3xl">
            <div class="absolute inset-0 rounded-full bg-gradient-to-b from-transparent via-violet-400/20 dark:via-violet-600/30 to-transparent -rotate-[42deg]"></div>
        </div>
        <div class="absolute w-[960px] h-24 -top-12 left-1/4 animate-[swing_15s_-1s_ease-in-out_infinite] blur-3xl">
            <div class="absolute inset-0 rounded-full bg-gradient-to-b from-transparent via-purple-300/15 dark:via-purple-400/20 to-transparent -rotate-[42deg]"></div>
        </div>
        <div class="absolute w-[960px] h-64 bottom-24 right-1/4 animate-[swing_10s_ease-in-out_infinite] blur-3xl">
            <div class="absolute inset-0 rounded-full bg-gradient-to-b from-transparent via-violet-400/10 dark:via-violet-600/10 to-transparent -rotate-[42deg]"></div>
        </div>
    </div>

    <!-- Site header -->
    <header class="sticky top-0 z-30 bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-16">

                <!-- Site branding -->
                <a href="/" class="flex items-center gap-3 group">
                    @if($workspace?->icon)
                        <div class="w-10 h-10 rounded-lg bg-{{ $workspace->color ?? 'violet' }}-500/20 flex items-center justify-center">
                            <i class="fa-solid fa-{{ $workspace->icon }} text-{{ $workspace->color ?? 'violet' }}-400"></i>
                        </div>
                    @else
                        <img src="{{ $appIcon }}" alt="{{ $appName }}" class="w-10 h-10">
                    @endif
                    <span class="font-bold text-lg text-slate-700 dark:text-slate-200 group-hover:text-slate-900 dark:group-hover:text-white transition">
                        {{ $workspace?->name ?? $appName }}
                    </span>
                </a>

                <!-- Navigation -->
                <nav class="hidden sm:flex items-center gap-6">
                    @if($workspace)
                        <a href="/" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">Home</a>
                        <a href="/blog" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">Blog</a>
                    @endif
                </nav>

                <!-- Actions -->
                <div class="flex items-center gap-4">
                    <!-- Theme toggle -->
                    <button
                        @click="darkMode = !darkMode"
                        class="flex items-center justify-center w-9 h-9 rounded-full text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        :aria-label="darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
                    >
                        <i class="fa-solid fa-sun-bright" x-show="darkMode" x-cloak></i>
                        <i class="fa-solid fa-moon-stars" x-show="!darkMode"></i>
                    </button>

                    <a href="{{ $appUrl }}" target="_blank" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition flex items-center gap-1">
                        <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
                        <span class="hidden sm:inline">Powered by {{ $appName }}</span>
                    </a>
                </div>

            </div>
        </div>
        <!-- Header gradient border -->
        <div class="h-px w-full bg-gradient-to-r from-transparent via-violet-500/30 to-transparent"></div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 relative">
        <!-- Radial gradient glow at top of content -->
        <div class="absolute flex items-center justify-center top-0 -translate-y-1/2 left-1/2 -translate-x-1/2 pointer-events-none -z-10 w-[800px] aspect-square" aria-hidden="true">
            <div class="absolute inset-0 translate-z-0 bg-violet-500 rounded-full blur-[120px] opacity-10 dark:opacity-20"></div>
            <div class="absolute w-64 h-64 translate-z-0 bg-purple-400 rounded-full blur-[80px] opacity-20 dark:opacity-40"></div>
        </div>

        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="mt-auto">
        <!-- Footer gradient border -->
        <div class="h-px w-full bg-gradient-to-r from-transparent via-violet-500/20 to-transparent"></div>

        {{-- Custom footer content (above default) --}}
        @if($footerHasCustom && $footerPosition === 'above_default')
            @include('front::components.satellite.footer-custom', [
                'customContent' => $footerCustomContent,
                'customLinks' => $footerCustomLinks,
                'socialLinks' => $footerSocialLinks,
                'contactEmail' => $footerContactEmail,
                'contactPhone' => $footerContactPhone,
            ])
        @endif

        {{-- Custom footer content (replace default) --}}
        @if($footerHasCustom && $footerPosition === 'replace_default')
            @include('front::components.satellite.footer-custom', [
                'customContent' => $footerCustomContent,
                'customLinks' => $footerCustomLinks,
                'socialLinks' => $footerSocialLinks,
                'contactEmail' => $footerContactEmail,
                'contactPhone' => $footerContactPhone,
                'showCopyright' => true,
                'copyrightText' => $footerCopyright,
                'workspaceName' => $workspace?->name,
                'appName' => $appName,
                'appIcon' => $appIcon,
            ])
        @endif

        {{-- Default footer --}}
        @if($footerShowDefault && $footerPosition !== 'replace_default')
            <div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <img src="{{ $appIcon }}" alt="{{ $appName }}" class="w-6 h-6 opacity-50">
                        <span class="text-sm text-slate-500">
                            {!! $footerCopyright ?? '&copy; '.date('Y').' '.e($workspace?->name ?? $appName) !!}
                        </span>
                    </div>
                    <div class="flex items-center gap-6 text-sm text-slate-500">
                        <a href="{{ $privacyUrl }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition">Privacy</a>
                        <a href="{{ $termsUrl }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition">Terms</a>
                        <a href="{{ $appUrl }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition flex items-center gap-1">
                            <i class="fa-solid fa-bolt text-violet-400 text-xs"></i>
                            Powered by {{ $appName }}
                        </a>
                    </div>
                </div>
            </div>
        @endif

        {{-- Custom footer content (below default) --}}
        @if($footerHasCustom && $footerPosition === 'below_default')
            @include('front::components.satellite.footer-custom', [
                'customContent' => $footerCustomContent,
                'customLinks' => $footerCustomLinks,
                'socialLinks' => $footerSocialLinks,
                'contactEmail' => $footerContactEmail,
                'contactPhone' => $footerContactPhone,
            ])
        @endif
    </footer>

</body>
</html>
