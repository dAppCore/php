@props([
    'title' => 'Admin',
    'sidebar' => null,
    'header' => null,
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
        html { background-color: #f3f4f6; }
        html.dark { background-color: #111827; }
    </style>

    <script @cspnonce>
        (function () {
            var darkMode = localStorage.getItem('dark-mode');
            if (darkMode === 'true') {
                localStorage.setItem('flux.appearance', 'dark');
            } else if (darkMode === 'false') {
                localStorage.setItem('flux.appearance', 'light');
            }
            document.cookie = 'dark-mode=' + (darkMode || 'false') + '; path=/; SameSite=Lax';
        })();
    </script>

    <!-- Fonts -->
    @include('layouts::partials.fonts')

    <!-- FontAwesome -->
    <link rel="stylesheet" href="{{ \Core\Helpers\Cdn::versioned('vendor/fontawesome/css/all.min.css') }}">

    <!-- Scripts -->
    @vite(['resources/css/admin.css', 'resources/js/app.js'])

    <!-- Flux -->
    @fluxAppearance

    {{ $head ?? '' }}
</head>
<body
    class="font-inter antialiased bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-400 overscroll-none"
    :class="{ 'sidebar-expanded': sidebarExpanded }"
    x-data="{ sidebarOpen: false, sidebarExpanded: localStorage.getItem('sidebar-expanded') == 'true' }"
    x-init="$watch('sidebarExpanded', value => localStorage.setItem('sidebar-expanded', value))"
>

<script @cspnonce>
    if (localStorage.getItem('sidebar-expanded') == 'true') {
        document.querySelector('body').classList.add('sidebar-expanded');
    } else {
        document.querySelector('body').classList.remove('sidebar-expanded');
    }
</script>

<!-- Page wrapper -->
<div class="flex h-[100dvh] overflow-hidden overscroll-none">

    <!-- Sidebar slot -->
    {{ $sidebar }}

    <!-- Content area -->
    <div
        class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden overscroll-none ml-0 lg:ml-20 lg:sidebar-expanded:ml-64 2xl:ml-64!"
        x-ref="contentarea">

        <!-- Header slot -->
        {{ $header }}

        <main class="grow px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
            {{ $slot }}
        </main>

    </div>

</div>

<!-- Flux Scripts -->
@fluxScripts

{{ $scripts ?? '' }}

<script @cspnonce>
    // Light/Dark mode toggle (guarded for Livewire navigation)
    (function() {
        if (window.__lightSwitchInitialized) return;
        window.__lightSwitchInitialized = true;

        const lightSwitch = document.querySelector('.light-switch');
        if (lightSwitch) {
            lightSwitch.addEventListener('change', () => {
                const {checked} = lightSwitch;
                document.documentElement.classList.toggle('dark', checked);
                document.documentElement.style.colorScheme = checked ? 'dark' : 'light';
                localStorage.setItem('dark-mode', checked);
            });
        }
    })();
</script>
</body>
</html>
