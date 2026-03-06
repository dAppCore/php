{{--
Authorization-aware toggle/switch component.

Wraps flux:switch with built-in authorization checking.

Usage:
<x-forms.toggle id="is_public" wire:model="is_public" label="Public" />
<x-forms.toggle id="is_public" wire:model="is_public" label="Public" instantSave />
<x-forms.toggle id="is_public" wire:model="is_public" label="Public" canGate="update" :canResource="$page" />
--}}

@props([
    'id',
    'label' => null,
    'helper' => null,
    'canGate' => null,
    'canResource' => null,
    'instantSave' => false,
])

@php
    $disabled = $attributes->get('disabled', false);
    if ($canGate && $canResource && !$disabled) {
        $disabled = !auth()->user()?->can($canGate, $canResource);
    }

    $wireModel = $attributes->wire('model')->value();
@endphp

<flux:field>
    <div class="flex items-center justify-between">
        @if($label)
            <flux:label>{{ $label }}</flux:label>
        @endif

        <flux:switch
            {{ $attributes->except(['disabled', 'wire:model', 'wire:model.live'])->merge([
                'id' => $id,
                'name' => $id,
                'disabled' => $disabled,
            ]) }}
            @if($wireModel)
                @if($instantSave)
                    wire:model.live="{{ $wireModel }}"
                @else
                    wire:model="{{ $wireModel }}"
                @endif
            @endif
        />
    </div>

    @if($helper)
        <flux:description>{{ $helper }}</flux:description>
    @endif

    <flux:error name="{{ $id }}" />
</flux:field>
