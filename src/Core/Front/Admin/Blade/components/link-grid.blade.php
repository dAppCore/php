<div {{ $attributes->merge(['class' => "grid {$gridCols} gap-4"]) }}>
    @foreach($items as $item)
        <a href="{{ $item['href'] }}" wire:navigate class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
            <div class="flex items-center gap-3">
                <core:icon :name="$itemIcon($item)" class="w-5 h-5 text-{{ $itemColor($item) }}-500" />
                <span class="font-medium text-gray-800 dark:text-gray-100">{{ $item['label'] }}</span>
            </div>
        </a>
    @endforeach
</div>
