@php
    // Color class mappings for Tailwind JIT purging
    $colorBgClass = match($color) {
        'violet' => 'bg-violet-500/20',
        'blue' => 'bg-blue-500/20',
        'green' => 'bg-green-500/20',
        'red' => 'bg-red-500/20',
        'amber' => 'bg-amber-500/20',
        'emerald' => 'bg-emerald-500/20',
        'cyan' => 'bg-cyan-500/20',
        'pink' => 'bg-pink-500/20',
        default => 'bg-gray-500/20',
    };
    $colorTextClass = match($color) {
        'violet' => 'text-violet-500',
        'blue' => 'text-blue-500',
        'green' => 'text-green-500',
        'red' => 'text-red-500',
        'amber' => 'text-amber-500',
        'emerald' => 'text-emerald-500',
        'cyan' => 'text-cyan-500',
        'pink' => 'text-pink-500',
        default => 'text-gray-500',
    };
    $colorHoverClass = match($color) {
        'violet' => 'hover:text-violet-500',
        'blue' => 'hover:text-blue-500',
        'green' => 'hover:text-green-500',
        'red' => 'hover:text-red-500',
        'amber' => 'hover:text-amber-500',
        'emerald' => 'hover:text-emerald-500',
        'cyan' => 'hover:text-cyan-500',
        'pink' => 'hover:text-pink-500',
        default => 'hover:text-gray-500',
    };
    $colorLinkClass = match($color) {
        'violet' => 'text-violet-500 hover:text-violet-600',
        'blue' => 'text-blue-500 hover:text-blue-600',
        'green' => 'text-green-500 hover:text-green-600',
        'red' => 'text-red-500 hover:text-red-600',
        'amber' => 'text-amber-500 hover:text-amber-600',
        'emerald' => 'text-emerald-500 hover:text-emerald-600',
        'cyan' => 'text-cyan-500 hover:text-cyan-600',
        'pink' => 'text-pink-500 hover:text-pink-600',
        default => 'text-gray-500 hover:text-gray-600',
    };
    $statusBgClass = match($statusColor) {
        'green' => 'bg-green-500',
        'red' => 'bg-red-500',
        'amber' => 'bg-amber-500',
        'blue' => 'bg-blue-500',
        default => 'bg-gray-500',
    };
    $statusTextClass = match($statusColor) {
        'green' => 'text-green-500',
        'red' => 'text-red-500',
        'amber' => 'text-amber-500',
        'blue' => 'text-blue-500',
        default => 'text-gray-500',
    };
@endphp
<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 shadow-xs rounded-xl overflow-hidden']) }}>
    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-lg {{ $colorBgClass }} flex items-center justify-center mr-3">
                    <core:icon :name="$icon" class="{{ $colorTextClass }} text-lg" />
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">{{ $name }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $description }}</p>
                </div>
            </div>
            <div class="flex items-center">
                <div class="w-2 h-2 rounded-full {{ $statusBgClass }} mr-1.5"></div>
                <span class="text-xs {{ $statusTextClass }} capitalize">{{ $status }}</span>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="px-5 py-4">
        @if(count($stats))
            <div class="grid grid-cols-3 gap-4 mb-4">
                @foreach($stats as $stat)
                    <div class="text-center">
                        <div class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ $stat['value'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        @if(count($actions))
            <div class="flex gap-2">
                @foreach($actions as $action)
                    <a href="{{ $action['route'] }}" class="flex-1 flex items-center justify-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700/30 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 transition">
                        <core:icon :name="$action['icon']" class="mr-2 {{ $colorTextClass }}" />
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Footer --}}
    <div class="px-5 py-3 bg-gray-50 dark:bg-gray-700/20 border-t border-gray-100 dark:border-gray-700/60">
        <div class="flex items-center justify-between text-xs">
            <a href="https://{{ $domain }}" target="_blank" class="text-gray-500 dark:text-gray-400 {{ $colorHoverClass }} transition">
                <core:icon name="arrow-up-right-from-square" class="mr-1" />
                {{ $domain }}
            </a>
            <div class="flex items-center gap-3">
                @if($adminRoute)
                    <a href="{{ $adminRoute }}" class="text-violet-500 hover:text-violet-600 font-medium">
                        <core:icon name="cog" class="mr-1" />
                        Admin
                    </a>
                @endif
                @if($detailsRoute)
                    <a href="{{ $detailsRoute }}" class="{{ $colorLinkClass }} font-medium">
                        View Details <core:icon name="chevron-right" class="ml-1" />
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
