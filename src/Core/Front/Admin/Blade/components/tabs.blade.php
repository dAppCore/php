@props([
    'tabs' => [],
    'selected' => null,
])

<core:tabs {{ $attributes }} class="mb-6" scrollable scrollable:fade scrollable:scrollbar="hide">
    @foreach($tabs as $key => $config)
        <core:tab
            name="{{ $key }}"
            :icon="$config['icon'] ?? null"
            :selected="$selected === $key"
            :href="$config['href'] ?? null"
            wire:navigate
        >{{ $config['label'] }}</core:tab>
    @endforeach
</core:tabs>
