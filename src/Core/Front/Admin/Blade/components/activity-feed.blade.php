<admin:panel :title="$title" :action="$action" :actionLabel="$actionLabel" {{ $attributes }}>
    @if(empty($items))
        <admin:empty-state :message="$empty" :icon="$emptyIcon" />
    @else
        <ul class="divide-y divide-gray-100 dark:divide-gray-700/50">
            @foreach($items as $item)
                <li class="group flex items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors duration-150 cursor-default">
                    {{-- Activity type indicator --}}
                    <div class="relative mt-0.5">
                        {{-- Coloured status dot --}}
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $itemColor($item) }}-500 ring-4 ring-{{ $itemColor($item) }}-500/20"></div>
                    </div>

                    {{-- Icon with background --}}
                    <div class="w-9 h-9 rounded-full shrink-0 bg-{{ $itemColor($item) }}-100 dark:bg-{{ $itemColor($item) }}-900/30 flex items-center justify-center">
                        <core:icon :name="$itemIcon($item)" class="w-4 h-4 text-{{ $itemColor($item) }}-600 dark:text-{{ $itemColor($item) }}-400" />
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100 leading-snug">{{ $item['message'] ?? '' }}</p>
                        @if(isset($item['subtitle']))
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $item['subtitle'] }}</p>
                        @endif
                    </div>

                    {{-- Timestamp --}}
                    @if(isset($item['time']))
                        <div class="shrink-0 text-right">
                            <time class="text-xs font-medium text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $item['time'] }}</time>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</admin:panel>
