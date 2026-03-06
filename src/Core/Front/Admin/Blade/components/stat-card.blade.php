@props([
    'value',
    'label',
    'icon' => 'chart-bar',
    'color' => 'violet',
    'change' => null,
    'changeLabel' => null,
])

<div {{ $attributes->merge(['class' => 'relative bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden']) }}>
    {{-- Coloured left border accent --}}
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-{{ $color }}-500"></div>

    <div class="p-5 pl-6">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                {{-- Label first (smaller, secondary) --}}
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">{{ $label }}</p>

                {{-- Value (larger, bolder, primary) --}}
                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 tabular-nums">{{ $value }}</p>

                {{-- Optional change indicator --}}
                @if($change !== null)
                    <div class="flex items-center gap-1 mt-2">
                        @if($change > 0)
                            <core:icon name="arrow-trending-up" class="w-4 h-4 text-green-500" />
                            <span class="text-xs font-medium text-green-600 dark:text-green-400">+{{ $change }}%</span>
                        @elseif($change < 0)
                            <core:icon name="arrow-trending-down" class="w-4 h-4 text-red-500" />
                            <span class="text-xs font-medium text-red-600 dark:text-red-400">{{ $change }}%</span>
                        @else
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">No change</span>
                        @endif
                        @if($changeLabel)
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $changeLabel }}</span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Icon with background circle --}}
            <div class="w-12 h-12 rounded-full bg-{{ $color }}-100 dark:bg-{{ $color }}-900/30 flex items-center justify-center shrink-0">
                <core:icon :name="$icon" class="w-6 h-6 text-{{ $color }}-600 dark:text-{{ $color }}-400" />
            </div>
        </div>
    </div>
</div>
