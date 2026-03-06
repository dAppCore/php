<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center">
    <!-- Background gradient -->
    <div class="fixed inset-0 -z-10">
        <div class="absolute inset-0 bg-gradient-to-b from-slate-900 via-slate-900 to-slate-950"></div>
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[600px] bg-violet-500/10 blur-[120px] rounded-full"></div>
    </div>

    {{ $slot }}

    @livewireScripts

</body>
</html>
