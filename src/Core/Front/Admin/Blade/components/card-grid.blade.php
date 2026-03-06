{{--
Card Grid Component

Each card in $cards array:
- id: unique identifier
- icon: icon name or null
- iconType: 'icon' (default), 'brand' (fa-brands), 'image' (url)
- iconColor: color name (violet, blue, green, etc)
- title: main title
- subtitle: subtitle text (optional)
- status: { label: 'Online', color: 'green' } (optional)
- stats: [{ label: 'CPU', value: '45%', progress: 45, progressColor: 'green' }] (optional)
- details: [{ label: 'Type', value: 'CMS' }] (optional)
- footer: [{ label: 'Visit', icon: 'arrow-up-right', href: 'url' }] (optional)
- menu: [{ label: 'Settings', icon: 'cog', href: 'url' or click: 'method' }] (optional)
--}}

@if(empty($cards))
    <div class="rounded-lg bg-white p-12 text-center shadow-sm dark:bg-gray-800">
        <core:icon name="{{ $emptyIcon }}" class="mx-auto mb-3 size-12 text-gray-300 dark:text-gray-600" />
        <p class="text-gray-500 dark:text-gray-400">{{ $empty }}</p>
    </div>
@else
    <div class="grid {{ $colsClass }} gap-6">
        @foreach($cards as $card)
            <div class="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800" wire:key="card-{{ $card['id'] ?? $loop->index }}">
                {{-- Card Header --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700/60">
                    <div class="flex items-center gap-3">
                        @if(isset($card['icon']))
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-{{ $card['iconColor'] ?? 'violet' }}-500/20">
                                @if(($card['iconType'] ?? 'icon') === 'brand')
                                    <i class="fa-brands fa-{{ $card['icon'] }} text-{{ $card['iconColor'] ?? 'violet' }}-500"></i>
                                @elseif(($card['iconType'] ?? 'icon') === 'image')
                                    <img src="{{ $card['icon'] }}" alt="" class="h-6 w-6 object-contain" />
                                @else
                                    <core:icon name="{{ $card['icon'] }}" class="text-{{ $card['iconColor'] ?? 'violet' }}-500" />
                                @endif
                            </div>
                        @endif
                        <div>
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">{{ $card['title'] }}</h3>
                            @if(isset($card['subtitle']))
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $card['subtitle'] }}</p>
                            @endif
                        </div>
                    </div>

                    @if(isset($card['menu']))
                        <div x-data="{ open: false }" class="relative">
                            <button
                                type="button"
                                @click="open = !open"
                                class="rounded-full p-1 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                            >
                                <core:icon name="ellipsis-vertical" class="size-5" />
                            </button>
                            <div
                                x-show="open"
                                @click.outside="open = false"
                                x-transition
                                class="absolute right-0 z-10 mt-1 min-w-36 rounded-lg border border-gray-200 bg-white py-1.5 shadow-lg dark:border-gray-700/60 dark:bg-gray-800"
                            >
                                @foreach($card['menu'] as $menuItem)
                                    @if(isset($menuItem['divider']))
                                        <div class="my-1 border-t border-gray-200 dark:border-gray-700/60"></div>
                                    @elseif(isset($menuItem['href']))
                                        <a
                                            href="{{ $menuItem['href'] }}"
                                            wire:navigate
                                            class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium {{ $menuItem['class'] ?? 'text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-200' }}"
                                        >
                                            @if(isset($menuItem['icon']))
                                                <core:icon name="{{ $menuItem['icon'] }}" class="size-4 text-gray-400" />
                                            @endif
                                            {{ $menuItem['label'] }}
                                        </a>
                                    @elseif(isset($menuItem['click']))
                                        <button
                                            type="button"
                                            wire:click="{{ $menuItem['click'] }}"
                                            @if(isset($menuItem['confirm'])) wire:confirm="{{ $menuItem['confirm'] }}" @endif
                                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm font-medium {{ $menuItem['class'] ?? 'text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-200' }}"
                                            @if(isset($menuItem['disabled']) && $menuItem['disabled']) disabled title="{{ $menuItem['disabledReason'] ?? '' }}" @endif
                                        >
                                            @if(isset($menuItem['icon']))
                                                <core:icon name="{{ $menuItem['icon'] }}" class="size-4 text-gray-400" />
                                            @endif
                                            {{ $menuItem['label'] }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Card Body --}}
                <div class="px-5 py-4">
                    @if(isset($card['status']))
                        <div class="mb-4 flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Status</span>
                            <div class="flex items-center">
                                <div class="mr-2 h-2 w-2 rounded-full bg-{{ $card['status']['color'] ?? 'gray' }}-500"></div>
                                <span class="text-sm font-medium text-{{ $card['status']['color'] ?? 'gray' }}-500">{{ $card['status']['label'] }}</span>
                            </div>
                        </div>
                    @endif

                    @if(isset($card['details']))
                        @foreach($card['details'] as $detail)
                            <div class="mb-4 flex items-center justify-between">
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $detail['label'] }}</span>
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $detail['value'] }}</span>
                            </div>
                        @endforeach
                    @endif

                    @if(isset($card['stats']))
                        @foreach($card['stats'] as $stat)
                            <div class="mb-4">
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</span>
                                    <span class="text-sm text-gray-800 dark:text-gray-100">{{ $stat['value'] }}</span>
                                </div>
                                @if(isset($stat['progress']))
                                    <div class="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div class="h-1.5 rounded-full bg-{{ $progressColor($stat) }}-500" style="width: {{ $stat['progress'] }}%"></div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endif

                    @if(isset($card['timestamp']))
                        <div class="flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-700/60">
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $card['timestamp']['label'] ?? 'Last updated' }}</span>
                            <span class="text-xs text-gray-600 dark:text-gray-300">{{ $card['timestamp']['value'] }}</span>
                        </div>
                    @endif
                </div>

                @if(isset($card['footer']))
                    <div class="flex justify-between gap-4 border-t border-gray-100 bg-gray-50 px-5 py-3 dark:border-gray-700/60 dark:bg-gray-700/20">
                        @foreach($card['footer'] as $action)
                            @if(isset($action['href']))
                                <a
                                    href="{{ $action['href'] }}"
                                    @if($action['external'] ?? false) target="_blank" @else wire:navigate @endif
                                    class="text-sm font-medium text-violet-500 hover:text-violet-600"
                                >
                                    @if(isset($action['icon']))
                                        <core:icon name="{{ $action['icon'] }}" class="mr-1 inline size-4" />
                                    @endif
                                    {{ $action['label'] }}
                                </a>
                            @elseif(isset($action['click']))
                                <button
                                    type="button"
                                    wire:click="{{ $action['click'] }}"
                                    class="text-sm font-medium text-violet-500 hover:text-violet-600"
                                >
                                    @if(isset($action['icon']))
                                        <core:icon name="{{ $action['icon'] }}" class="mr-1 inline size-4" />
                                    @endif
                                    {{ $action['label'] }}
                                </button>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
