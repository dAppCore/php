@props([
    'title' => '',
    'value' => '',
    'icon' => 'chart-bar',
    'color' => 'gray',
])

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6']) }}>
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $title }}</h3>
        <core:icon :name="$icon" class="text-{{ $color }}-500" />
    </div>
    <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $value }}</div>
    @if($slot->isNotEmpty())
        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $slot }}</div>
    @endif
</div>
