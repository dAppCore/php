<ul class="space-y-1">
    @foreach($items as $item)
        @if(!empty($item['divider']))
            {{-- Divider (with optional label) --}}
            <li class="py-2">
                @if(!empty($item['label']))
                    <div class="flex items-center gap-2">
                        <hr class="flex-1 border-gray-200 dark:border-gray-700" />
                        <span class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ $item['label'] }}</span>
                        <hr class="flex-1 border-gray-200 dark:border-gray-700" />
                    </div>
                @else
                    <hr class="border-gray-200 dark:border-gray-700" />
                @endif
            </li>
        @elseif(!empty($item['separator']))
            {{-- Simple separator --}}
            <li class="py-1">
                <hr class="border-gray-100 dark:border-gray-800" />
            </li>
        @elseif(!empty($item['collapsible']))
            {{-- Collapsible group --}}
            @php
                $collapsibleId = 'menu-group-' . ($item['stateKey'] ?? \Illuminate\Support\Str::slug($item['label']));
                $isOpen = $item['open'] ?? true;
                $groupColor = match($item['color'] ?? 'gray') {
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
            @endphp
            <li x-data="{ open: {{ $isOpen ? 'true' : 'false' }} }" class="group">
                <button
                    type="button"
                    @click="open = !open"
                    class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold uppercase tracking-wider {{ $groupColor }} hover:bg-gray-50 dark:hover:bg-gray-800 rounded-md transition-colors"
                >
                    <span class="flex items-center gap-2">
                        @if(!empty($item['icon']))
                            <core:icon :name="$item['icon']" class="size-4 shrink-0" />
                        @endif
                        {{ $item['label'] }}
                    </span>
                    <core:icon name="chevron-down" class="size-4 shrink-0 transition-transform duration-200" x-bind:class="{ 'rotate-180': open }" />
                </button>
                <ul x-show="open" x-collapse class="mt-1 ml-2 pl-2 border-l border-gray-200 dark:border-gray-700 space-y-1">
                    @foreach($item['children'] ?? [] as $child)
                        @if(!empty($child['separator']))
                            <li class="py-1"><hr class="border-gray-100 dark:border-gray-800" /></li>
                        @elseif(!empty($child['section']))
                            @php
                                $childColor = match($child['color'] ?? 'gray') {
                                    'violet' => 'text-violet-500',
                                    'blue' => 'text-blue-500',
                                    'green' => 'text-green-500',
                                    'red' => 'text-red-500',
                                    'amber' => 'text-amber-500',
                                    'emerald' => 'text-emerald-500',
                                    'cyan' => 'text-cyan-500',
                                    'pink' => 'text-pink-500',
                                    default => 'text-gray-400 dark:text-gray-500',
                                };
                            @endphp
                            <li class="pt-2 pb-1 first:pt-0 flex items-center gap-2">
                                @if(!empty($child['icon']))
                                    <core:icon :name="$child['icon']" class="size-3 shrink-0 {{ $childColor }}" />
                                @endif
                                <span class="text-[10px] font-semibold uppercase tracking-wider {{ $childColor }}">{{ $child['section'] }}</span>
                            </li>
                        @else
                            <admin:nav-link
                                :href="$child['href'] ?? '#'"
                                :active="$child['active'] ?? false"
                                :badge="$child['badge'] ?? null"
                                :icon="$child['icon'] ?? null"
                                :color="$child['color'] ?? null"
                            >{{ $child['label'] }}</admin:nav-link>
                        @endif
                    @endforeach
                </ul>
            </li>
        @elseif(!empty($item['children']))
            {{-- Dropdown menu with children --}}
            <li>
                <admin:nav-menu
                    :title="$item['label']"
                    :icon="$item['icon'] ?? null"
                    :active="$item['active'] ?? false"
                    :color="$item['color'] ?? 'gray'"
                >
                    @foreach($item['children'] as $child)
                        @if(!empty($child['separator']))
                            {{-- Separator within dropdown --}}
                            <li class="py-1">
                                <hr class="border-gray-100 dark:border-gray-800" />
                            </li>
                        @elseif(!empty($child['section']))
                            {{-- Section header within dropdown --}}
                            @php
                                $sectionIconClass = match($child['color'] ?? 'gray') {
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
                            @endphp
                            <li class="pt-3 pb-1 first:pt-1 flex items-center gap-2">
                                @if(!empty($child['icon']))
                                    <core:icon :name="$child['icon']" class="size-4 shrink-0 {{ $sectionIconClass }}" />
                                @endif
                                <span class="text-xs font-semibold uppercase tracking-wider {{ $sectionIconClass }}">
                                    {{ $child['section'] }}
                                </span>
                                @if(!empty($child['badge']))
                                    <span class="ml-auto text-xs px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                        {{ is_array($child['badge']) ? ($child['badge']['text'] ?? '') : $child['badge'] }}
                                    </span>
                                @endif
                            </li>
                        @else
                            <admin:nav-link
                                :href="$child['href'] ?? '#'"
                                :active="$child['active'] ?? false"
                                :badge="$child['badge'] ?? null"
                                :icon="$child['icon'] ?? null"
                                :color="$child['color'] ?? null"
                            >{{ $child['label'] }}</admin:nav-link>
                        @endif
                    @endforeach
                </admin:nav-menu>
            </li>
        @else
            {{-- Single nav item --}}
            <li>
                <admin:nav-item
                    :href="$item['href'] ?? '#'"
                    :icon="$item['icon'] ?? null"
                    :active="$item['active'] ?? false"
                    :color="$item['color'] ?? 'gray'"
                    :badge="$item['badge'] ?? null"
                >{{ $item['label'] }}</admin:nav-item>
            </li>
        @endif
    @endforeach
</ul>
