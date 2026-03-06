{{--
Authorization-aware input component.

Wraps flux:input with built-in authorization checking.

Usage:
<x-forms.input id="name" wire:model="name" label="Name" />
<x-forms.input id="name" wire:model="name" label="Name" canGate="update" :canResource="$page" />
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
    @if($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <flux:input
        {{ $attributes->except(['disabled', 'wire:model', 'wire:model.live'])->merge([
            'id' => $id,
            'name' => $id,
            'disabled' => $disabled,
        ]) }}
        @if($wireModel)
            @if($instantSave)
                wire:model.live.debounce.500ms="{{ $wireModel }}"
            @else
                wire:model="{{ $wireModel }}"
            @endif
        @endif
    />

    @if($helper)
        <flux:description>{{ $helper }}</flux:description>
    @endif

    <flux:error name="{{ $id }}" />
</flux:field>
