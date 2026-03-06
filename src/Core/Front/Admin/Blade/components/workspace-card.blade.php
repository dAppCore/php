@props([
    'workspace',
    'services' => [],
    'subscription' => null,
])

@php
    $hasServices = count($services) > 0;
    $workspaceColor = $workspace->color ?? 'violet';
    $workspaceIcon = $workspace->icon ?? 'folder';
@endphp

<div {{ $attributes->merge(['class' => 'col-span-full sm:col-span-6 xl:col-span-4 bg-white dark:bg-gray-800 shadow-xs rounded-xl']) }}>
    <div class="flex flex-col h-full">
        {{-- Card top --}}
        <div class="grow p-5">
            <div class="flex justify-between items-start">
                {{-- Icon + name --}}
                <header>
                    <div class="flex mb-2">
                        <a href="{{ route('hub.sites.settings', ['workspace' => $workspace->slug]) }}" wire:navigate class="relative inline-flex items-start mr-4">
                            <div class="w-12 h-12 rounded-full bg-{{ $workspaceColor }}-100 dark:bg-{{ $workspaceColor }}-500/20 flex items-center justify-center">
                                <core:icon :name="$workspaceIcon" class="w-6 h-6 text-{{ $workspaceColor }}-500" />
                            </div>
                        </a>
                        <div class="mt-1 pr-1">
                            <a href="{{ route('hub.sites.settings', ['workspace' => $workspace->slug]) }}" wire:navigate class="inline-flex text-gray-800 dark:text-gray-100 hover:text-gray-900 dark:hover:text-white">
                                <h2 class="text-xl leading-snug font-semibold">{{ $workspace->name }}</h2>
                            </a>
                            @if($workspace->domain)
                                <div class="text-sm text-gray-400 dark:text-gray-500">{{ $workspace->domain }}</div>
                            @endif
                        </div>
                    </div>
                </header>
                {{-- Subscription badge --}}
                @if($subscription)
                    <flux:badge color="green" variant="pill" size="sm">
                        {{ ucfirst($subscription->status) }}
                    </flux:badge>
                @endif
            </div>

            {{-- Services list --}}
            <div class="mt-4">
                @if($hasServices)
                    <div class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase mb-2">{{ __('hub::hub.dashboard.enabled_services') }}</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($services as $serviceKey => $service)
                            @php
                                $serviceHref = $service['href'] ?? ($service['children'][0]['href'] ?? route('hub.services', ['service' => $serviceKey]));
                            @endphp
                            <flux:badge
                                as="a"
                                href="{{ $serviceHref }}"
                                wire:navigate
                                :color="$service['color'] ?? 'zinc'"
                                size="sm"
                                variant="pill"
                            >
                                @if($service['icon'] ?? null)
                                    <core:icon :name="$service['icon']" class="w-3 h-3 mr-1" />
                                @endif
                                {{ $service['label'] }}
                            </flux:badge>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6">
                        <svg class="w-10 h-10 text-gray-200 dark:text-gray-700 mx-auto mb-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.39 48.39 0 0 1-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 0 1-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 0 0-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 0 1-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 0 0 .657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 0 1-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 0 0 5.427-.63 48.05 48.05 0 0 0 .582-4.717.532.532 0 0 0-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 0 0 .658-.663 48.422 48.422 0 0 0-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 0 1-.61-.58v0Z" />
                        </svg>
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('hub::hub.dashboard.no_services') }}</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Card footer --}}
        <div class="border-t border-gray-100 dark:border-gray-700/60 px-5 py-3">
            @if($hasServices)
                <flux:button href="{{ route('hub.sites.settings', ['workspace' => $workspace->slug]) }}" wire:navigate variant="ghost" icon="pencil-square" class="w-full justify-center">
                    {{ __('hub::hub.dashboard.manage_workspace') }}
                </flux:button>
            @else
                <flux:button href="{{ route('hub.sites.settings', ['workspace' => $workspace->slug]) }}" wire:navigate variant="subtle" icon="plus" class="w-full justify-center">
                    {{ __('hub::hub.dashboard.add_services') }}
                </flux:button>
            @endif
        </div>
    </div>
</div>
