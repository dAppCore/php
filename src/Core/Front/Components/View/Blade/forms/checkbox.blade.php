{{--
Authorization-aware checkbox component.

Wraps flux:checkbox with built-in authorization checking.

Usage:
<x-forms.checkbox id="notify" wire:model="notify" label="Send notifications" />
<x-forms.checkbox id="notify" wire:model="notify" label="Send notifications" canGate="update" :canResource="$page" />
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
    <flux:checkbox
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
    >
        @if($label)
            {{ $label }}
        @endif
    </flux:checkbox>

    @if($helper)
        <flux:description>{{ $helper }}</flux:description>
    @endif

    <flux:error name="{{ $id }}" />
</flux:field>
