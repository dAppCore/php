@props([
    'title' => null,
    'description' => null,
    'ogImage' => null,
    'ogType' => 'website',
    'particles' => false,
])

@php
    $appName = config('core.app.name', 'Core PHP');
    $appTagline = config('core.app.tagline', 'Modular Monolith Framework');
    $defaultDescription = config('core.app.description', "{$appName} - {$appTagline}");
    $contactEmail = config('core.contact.email', 'hello@' . config('core.domain.base', 'core.test'));

    $pageTitle = $title ? $title . ' - ' . $appName : $appName . ' - ' . $appTagline;
    $pageDescription = $description ?? $defaultDescription;
    $pageOgImage = $ogImage ?? asset('images/og-default.jpg');
    $pageUrl = url()->current();
@endphp

        <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth overscroll-none">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">

    <!-- Canonical URL -->
    <link rel="canonical" href="{{ $pageUrl }}">

    <!-- Open Graph -->
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:image" content="{{ $pageOgImage }}">
    <meta property="og:url" content="{{ $pageUrl }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:site_name" content="{{ $appName }}">
    <meta property="og:locale" content="en_GB">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $pageOgImage }}">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@graph": [
                {
                    "@@type": "Organization",
                    "@@id": "{{ url('/') }}#organization",
                "name": "{{ $appName }}",
                "url": "{{ url('/') }}",
                "logo": {
                    "@@type": "ImageObject",
                    "url": "{{ asset(config('core.app.logo', '/images/logo.svg')) }}"
                },
                "sameAs": [],
                "contactPoint": {
                    "@@type": "ContactPoint",
                    "email": "{{ $contactEmail }}",
                    "contactType": "customer service"
                }
            },
            {
                "@@type": "WebSite",
                "@@id": "{{ url('/') }}#website",
                "url": "{{ url('/') }}",
                "name": "{{ $appName }}",
                "description": "{{ $appTagline }}",
                "publisher": {
                    "@@id": "{{ url('/') }}#organization"
                },
                "inLanguage": "en-GB"
            },
            {
                "@@type": "WebPage",
                "@@id": "{{ $pageUrl }}#webpage",
                "url": "{{ $pageUrl }}",
                "name": "{{ $pageTitle }}",
                "description": "{{ $pageDescription }}",
                "isPartOf": {
                    "@@id": "{{ url('/') }}#website"
                },
                "inLanguage": "en-GB"
            }
        ]
    }
    </script>

    <!-- Fonts -->
    @include('layouts::partials.fonts')

    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="{{ \Core\Helpers\Cdn::versioned('vendor/fontawesome/css/all.min.css') }}">

    <!-- Tailwind / Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Flux -->
    @fluxAppearance

    {{ $head ?? '' }}

    @stack('styles')
</head>
<body class="font-sans antialiased bg-slate-900 text-slate-100 tracking-tight overscroll-none">
<!-- Skip Navigation Link -->
<a href="#main-content"
   class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-purple-600 focus:text-white focus:rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-400">
    Skip to main content
</a>

@if($particles)
    <!-- Particle animation background -->
    <div class="fixed inset-0 z-0 pointer-events-none w-full h-full" aria-hidden="true">
        <canvas data-particle-animation data-particle-quantity="40" data-particle-staticity="50" data-particle-ease="50"
                class="w-full h-full"></canvas>
    </div>
@endif

{{ $slot }}

<!-- Developer Bar (Hades accounts only) -->
@include('hub::admin.components.developer-bar')

<!-- Flux Scripts -->
@fluxScripts

{{ $scripts ?? '' }}

@stack('scripts')

</body>
</html>
