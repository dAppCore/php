@props([
    'title' => config('core.app.name', 'Core PHP'),
])

@php
    $darkMode = request()->cookie('dark-mode') === 'true';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overscroll-none {{ $darkMode ? 'dark' : '' }}"
      style="color-scheme: {{ $darkMode ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }}</title>

    {{-- Critical CSS: Prevents white flash during page load/navigation --}}
    <style @cspnonce>
        html { background-color: #ffffff; }
        html.dark { background-color: #111827; }
    </style>

    <!-- Fonts -->
    @include('layouts::partials.fonts')

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Flux -->
    @fluxAppearance

    {{ $head ?? '' }}
</head>
<body class="font-inter antialiased bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 overscroll-none">

<div class="min-h-screen">
    {{ $slot }}
</div>

<!-- Flux Scripts -->
@fluxScripts

{{ $scripts ?? '' }}
</body>
</html>
