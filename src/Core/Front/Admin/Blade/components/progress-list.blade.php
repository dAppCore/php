<admin:panel :title="$title" :action="$action" :actionLabel="$actionLabel" {{ $attributes }}>
    @if(empty($items))
        <admin:empty-state :message="$empty" :icon="$emptyIcon" />
    @else
        <div class="space-y-4">
            @foreach($items as $item)
                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">{{ $item['label'] }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $formatValue($item['value'] ?? 0) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="bg-{{ $itemColor($item) }}-500 h-2 rounded-full transition-all" style="width: {{ $itemPercentage($item) }}%"></div>
                    </div>
                    @if(isset($item['subtitle']) || isset($item['badge']))
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-400 dark:text-gray-500">{{ $item['subtitle'] ?? '' }}</span>
                            @if(isset($item['badge']))
                                <span class="text-{{ $item['badgeColor'] ?? 'gray' }}-500">{{ $item['badge'] }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</admin:panel>
