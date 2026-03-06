<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} - lt.hn</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

    {{-- Font Awesome --}}
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css">

    @vite(['resources/css/admin.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="font-inter antialiased bg-[#070b0b] text-[#cccccb] min-h-screen">
    {{-- Header --}}
    <header class="sticky top-0 z-50 border-b border-[#40c1c5]/10 bg-[#070b0b]/95 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">
                {{-- Logo + Bio link --}}
                <div class="flex items-center gap-4">
                    <a href="{{ url('/') }}" class="text-xl font-bold text-white">lt.hn</a>
                    @if(isset($bioUrl))
                        <span class="text-[#cccccb]/40">/</span>
                        <a href="{{ url('/' . $bioUrl) }}" class="text-sm text-[#40c1c5] hover:text-[#5dd1d5] transition">
                            {{ $bioUrl }}
                        </a>
                    @endif
                </div>

                {{-- Nav + User menu --}}
                <div class="flex items-center gap-6">
                    @if(isset($bioUrl))
                        <nav class="flex items-center gap-1">
                            @php
                                $currentPath = request()->path();
                                $navItems = [
                                    ['url' => "/{$bioUrl}/settings", 'label' => 'Editor', 'icon' => 'fa-pen-to-square'],
                                    ['url' => "/{$bioUrl}/analytics", 'label' => 'Analytics', 'icon' => 'fa-chart-line'],
                                    ['url' => "/{$bioUrl}/submissions", 'label' => 'Submissions', 'icon' => 'fa-inbox'],
                                    ['url' => "/{$bioUrl}/qr", 'label' => 'QR Code', 'icon' => 'fa-qrcode'],
                                ];
                            @endphp
                            @foreach($navItems as $item)
                                <a href="{{ url($item['url']) }}"
                                   class="flex items-center gap-2 px-3 py-1.5 rounded-md text-sm transition
                                          {{ $currentPath === ltrim($item['url'], '/')
                                             ? 'bg-[#40c1c5]/10 text-[#40c1c5]'
                                             : 'text-[#cccccb]/60 hover:text-white hover:bg-white/5' }}">
                                    <i class="fa-solid {{ $item['icon'] }} text-xs"></i>
                                    <span class="hidden sm:inline">{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </nav>
                    @endif

                    @auth
                        <div class="flex items-center gap-4 pl-4 border-l border-[#40c1c5]/10">
                            <a href="{{ url('/dashboard') }}" class="text-sm text-[#cccccb]/60 hover:text-[#40c1c5] transition hidden md:inline">
                                Dashboard
                            </a>
                            <a href="{{ url('/logout') }}" class="text-sm text-[#cccccb]/60 hover:text-white transition">
                                Sign out
                            </a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    {{-- Main content --}}
    <main class="min-h-[calc(100vh-3.5rem)]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            {{ $slot }}
        </div>
    </main>

    @fluxScripts
</body>
</html>
