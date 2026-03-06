@php
    $color = $workspace['color'] ?? 'violet';
    $colorMap = [
        'violet' => ['accent' => '#8b5cf6', 'hover' => '#a78bfa'],
        'green' => ['accent' => '#22c55e', 'hover' => '#4ade80'],
        'yellow' => ['accent' => '#eab308', 'hover' => '#facc15'],
        'orange' => ['accent' => '#f97316', 'hover' => '#fb923c'],
        'red' => ['accent' => '#ef4444', 'hover' => '#f87171'],
        'cyan' => ['accent' => '#06b6d4', 'hover' => '#22d3ee'],
        'blue' => ['accent' => '#3b82f6', 'hover' => '#60a5fa'],
    ];
    $colors = $colorMap[$color] ?? $colorMap['violet'];
@endphp
<!DOCTYPE html>
<html lang="en" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? $workspace['name'] ?? config('core.app.name', 'Service') }}</title>
    <meta name="description" content="{{ $workspace['description'] ?? '' }}">

    <meta property="og:title" content="{{ $title ?? $workspace['name'] ?? config('core.app.name', 'Service') }}">
    <meta property="og:description" content="{{ $workspace['description'] ?? '' }}">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">

    @if(View::exists('layouts::partials.fonts'))
        @include('layouts::partials.fonts')
    @endif
    @if(file_exists(public_path('vendor/fontawesome/css/all.min.css')))
        <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if(class_exists(\Flux\Flux::class))
        @fluxAppearance
    @endif

    <style>
        :root {
            --service-accent: {{ $colors['accent'] }};
            --service-hover: {{ $colors['hover'] }};
        }
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
<body class="font-sans antialiased bg-slate-900 text-slate-100 tracking-tight min-h-screen flex flex-col overflow-x-hidden overscroll-none">

    {{-- Decorative background shapes --}}
    <div class="fixed inset-0 -z-10 pointer-events-none overflow-hidden" aria-hidden="true">
        <div class="absolute w-[960px] h-24 top-12 left-1/2 -translate-x-1/2 animate-[swing_8s_ease-in-out_infinite] blur-3xl">
            <div class="absolute inset-0 rounded-full -rotate-[42deg]" style="background: linear-gradient(to bottom, transparent, color-mix(in srgb, var(--service-accent) 30%, transparent), transparent);"></div>
        </div>
        <div class="absolute w-[960px] h-24 -top-12 left-1/4 animate-[swing_15s_-1s_ease-in-out_infinite] blur-3xl">
            <div class="absolute inset-0 rounded-full -rotate-[42deg]" style="background: linear-gradient(to bottom, transparent, color-mix(in srgb, var(--service-hover) 20%, transparent), transparent);"></div>
        </div>
        <div class="absolute w-[960px] h-64 bottom-24 right-1/4 animate-[swing_10s_ease-in-out_infinite] blur-3xl">
            <div class="absolute inset-0 rounded-full -rotate-[42deg]" style="background: linear-gradient(to bottom, transparent, color-mix(in srgb, var(--service-accent) 10%, transparent), transparent);"></div>
        </div>
    </div>

    {{-- Header --}}
    @include('service::components.header', ['workspace' => $workspace, 'colors' => $colors])

    {{-- Main Content --}}
    <main class="flex-1 relative">
        {{-- Radial gradient glow at top of content --}}
        <div class="absolute flex items-center justify-center top-0 -translate-y-1/2 left-1/2 -translate-x-1/2 pointer-events-none -z-10 w-[800px] aspect-square" aria-hidden="true">
            <div class="absolute inset-0 translate-z-0 rounded-full blur-[120px] opacity-20" style="background-color: var(--service-accent);"></div>
            <div class="absolute w-64 h-64 translate-z-0 rounded-full blur-[80px] opacity-40" style="background-color: var(--service-hover);"></div>
        </div>

        {{ $slot }}
    </main>

    {{-- Footer --}}
    @include('service::components.footer', ['workspace' => $workspace, 'colors' => $colors])

    @if(class_exists(\Flux\Flux::class))
        @fluxScripts
    @endif

    {{ $scripts ?? '' }}

    @stack('scripts')
</body>
</html>
