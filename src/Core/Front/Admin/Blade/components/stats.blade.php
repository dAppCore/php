<div {{ $attributes->merge(['class' => "grid {$gridCols} gap-4 mb-8"]) }}>
    @if(count($items))
        @foreach($items as $item)
            <admin:stat-card
                :value="$item['value']"
                :label="$item['label']"
                :icon="$item['icon'] ?? 'chart-bar'"
                :color="$item['color'] ?? 'violet'"
            />
        @endforeach
    @else
        {{ $slot }}
    @endif
</div>
