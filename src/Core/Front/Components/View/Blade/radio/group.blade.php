@props([
    'label' => null,         // Group label
    'description' => null,   // Help text
    'variant' => null,       // cards, segmented
    'wire:model' => null,
    'wire:model.live' => null,
])

<flux:radio.group {{ $attributes }}>
    {{ $slot }}
</flux:radio.group>
