<div {{ $attributes->merge(['class' => "grid {$gridCols} gap-4 mb-8"]) }}>
    @foreach($items as $item)
        <admin:metric-card
            :title="$item['title']"
            :value="$item['value']"
            :icon="$item['icon'] ?? 'chart-bar'"
            :color="$item['color'] ?? 'gray'"
        />
    @endforeach
</div>
