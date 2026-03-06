@props([
    'title' => 'Quick Actions',
    'items' => [],           // [{href, title, subtitle?, icon?, color?}]
])

<admin:panel :title="$title" {{ $attributes }}>
    <div class="space-y-3">
        @if(count($items))
            @foreach($items as $item)
                <admin:action-link
                    :href="$item['href']"
                    :title="$item['title']"
                    :subtitle="$item['subtitle'] ?? null"
                    :icon="$item['icon'] ?? 'arrow-right'"
                    :color="$item['color'] ?? 'violet'"
                />
            @endforeach
        @else
            {{ $slot }}
        @endif
    </div>
</admin:panel>
