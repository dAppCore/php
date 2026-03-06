@props(['title' => null, 'workspace' => []])

@php
    $appName = config('core.app.name', 'Core PHP');
    $baseDomain = config('core.domain.base', 'core.test');
    $hubUrl = 'https://hub.' . $baseDomain;
    $wsName = $workspace['name'] ?? $appName;
    $wsColor = $workspace['color'] ?? 'violet';
    $wsIcon = $workspace['icon'] ?? 'globe';
    $wsSlug = $workspace['slug'] ?? 'main';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth overscroll-none">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? $wsName . ' | ' . $appName }}</title>

    <!-- Fonts -->
    @include('layouts::partials.fonts')

    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css?v={{ filemtime(public_path('vendor/fontawesome/css/all.min.css')) }}">

    <!-- Tailwind / Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Flux -->
    @fluxAppearance
</head>
<body class="font-sans antialiased bg-slate-900 text-slate-100 tracking-tight overscroll-none">

    <!-- Page wrapper -->
    <div class="flex flex-col min-h-screen overflow-hidden supports-[overflow:clip]:overflow-clip overscroll-none">

        <!-- Header -->
        <header class="fixed w-full z-30 bg-slate-900/80 backdrop-blur-sm border-b border-slate-800">
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between h-16 md:h-20">

                    <!-- Site branding -->
                    <div class="flex-1">
                        <a class="inline-flex items-center gap-3" href="/" aria-label="{{ $wsName }}">
                            <div class="w-10 h-10 rounded-lg bg-{{ $wsColor }}-500/20 flex items-center justify-center">
                                <x-fa-icon :icon="$wsIcon" class="text-{{ $wsColor }}-500 text-xl" />
                            </div>
                            <span class="font-bold text-xl text-slate-200">{{ $wsName }}</span>
                        </a>
                    </div>

                    <!-- Desktop navigation -->
                    <nav class="hidden md:flex md:grow">
                        <ul class="flex grow justify-center flex-wrap items-center">
                            <li>
                                <a class="font-medium text-sm text-slate-300 hover:text-white mx-4 lg:mx-5 transition duration-150 ease-in-out" href="#features">
                                    Features
                                </a>
                            </li>
                            <li>
                                <a class="font-medium text-sm text-slate-300 hover:text-white mx-4 lg:mx-5 transition duration-150 ease-in-out" href="{{ $hubUrl }}/pricing">
                                    Pricing
                                </a>
                            </li>
                            <li>
                                <a class="font-medium text-sm text-slate-300 hover:text-white mx-4 lg:mx-5 transition duration-150 ease-in-out" href="{{ $hubUrl }}">
                                    {{ $appName }}
                                </a>
                            </li>
                        </ul>
                    </nav>

                    <!-- Desktop actions -->
                    <ul class="flex-1 flex justify-end items-center gap-3">
                        @auth
                            <li>
                                <a href="{{ $hubUrl }}/hub/content/{{ $wsSlug }}/posts" class="font-medium text-sm text-slate-300 hover:text-white transition duration-150 ease-in-out">
                                    <x-fa-icon icon="pen-to-square" class="mr-1" /> Manage
                                </a>
                            </li>
                            <li class="ml-2">
                                <a href="{{ $hubUrl }}/hub" class="btn-sm text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700 transition duration-150 ease-in-out">
                                    Dashboard
                                </a>
                            </li>
                        @else
                            <li>
                                <a class="font-medium text-sm text-slate-300 hover:text-white transition duration-150 ease-in-out" href="{{ $hubUrl }}/login">
                                    Login
                                </a>
                            </li>
                            <li class="ml-2">
                                <a href="{{ $hubUrl }}/waitlist" class="btn-sm text-slate-900 bg-gradient-to-r from-{{ $wsColor }}-400 via-{{ $wsColor }}-500 to-{{ $wsColor }}-400 hover:from-{{ $wsColor }}-300 hover:to-{{ $wsColor }}-300 transition duration-150 ease-in-out">
                                    Get early access
                                </a>
                            </li>
                        @endauth
                    </ul>

                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="grow pt-16 md:pt-20 flex flex-col">
            {{ $slot }}
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-800 mt-auto">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-8 h-8 rounded bg-{{ $wsColor }}-500/20 flex items-center justify-center">
                            <x-fa-icon :icon="$wsIcon" class="text-{{ $wsColor }}-500 text-sm" />
                        </div>
                        <div class="text-sm text-slate-400">
                            {{ $wsName }} is part of <a href="{{ $hubUrl }}" class="text-{{ $wsColor }}-400 hover:text-{{ $wsColor }}-300">{{ $appName }}</a>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-slate-500">
                        <a href="{{ $hubUrl }}/privacy" class="hover:text-slate-300 transition">Privacy</a>
                        <a href="{{ $hubUrl }}/terms" class="hover:text-slate-300 transition">Terms</a>
                        <span>&copy; {{ date('Y') }} {{ $appName }}</span>
                    </div>
                </div>
            </div>
        </footer>

    </div>

    <!-- Flux Scripts -->
    @fluxScripts
</body>
</html>
