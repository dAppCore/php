<admin:panel :title="$title" :action="$action" :actionLabel="$actionLabel" {{ $attributes }}>
    @if(empty($rows))
        <admin:empty-state :message="$empty" :icon="$emptyIcon" />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                @if(count($columns))
                    <thead>
                        <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                            @foreach($processedColumns as $col)
                                <th class="pb-2 {{ $col['align'] === 'right' ? 'text-right' : '' }}">{{ $col['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                @endif
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($rows as $row)
                        <tr>
                            @foreach($row as $i => $cell)
                                <td class="py-2 {{ $cellAlignClass($i) }}">
                                    @if(is_array($cell) && isset($cell['badge']))
                                        <core:badge :color="$cell['color'] ?? 'gray'">{{ $cell['badge'] }}</core:badge>
                                    @elseif(is_array($cell) && isset($cell['mono']))
                                        <span class="font-mono text-xs">{{ $cell['mono'] }}</span>
                                    @elseif(is_array($cell) && isset($cell['muted']))
                                        <span class="text-gray-600 dark:text-gray-300">{{ $cell['muted'] }}</span>
                                    @elseif(is_array($cell) && isset($cell['bold']))
                                        <span class="font-medium">{{ $cell['bold'] }}</span>
                                    @else
                                        {{ $cell }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</admin:panel>
