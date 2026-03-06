<div {{ $attributes->merge(['class' => "grid {$gridCols} gap-4 mb-8"]) }}>
    @foreach($items as $item)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-{{ $itemColor($item) }}-500">
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $item['value'] }}</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $item['label'] }}</div>
        </div>
    @endforeach
</div>
