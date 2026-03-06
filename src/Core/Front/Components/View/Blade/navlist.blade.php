@props([
    'variant' => null,       // outline
    'items' => [],           // [{label, href?, action?, current?, icon?, badge?}]
])

<flux:navlist {{ $attributes->except('items') }}>
    @if(count($items) > 0)
        @foreach($items as $item)
            <flux:navlist.item
                :href="$item['href'] ?? null"
                :wire:navigate="isset($item['href'])"
                :wire:click="$item['action'] ?? null"
                :current="$item['current'] ?? false"
                :icon="$item['icon'] ?? null"
                :badge="$item['badge'] ?? null"
            >
                {{ $item['label'] ?? '' }}
            </flux:navlist.item>
        @endforeach
    @else
        {{ $slot }}
    @endif
</flux:navlist>
