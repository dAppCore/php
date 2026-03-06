{{--
Authorization-aware textarea component.

Wraps flux:textarea with built-in authorization checking.

Usage:
<x-forms.textarea id="bio" wire:model="bio" label="Bio" rows="4" />
<x-forms.textarea id="bio" wire:model="bio" label="Bio" canGate="update" :canResource="$page" />
--}}

@props([
    'id',
    'label' => null,
    'helper' => null,
    'canGate' => null,
    'canResource' => null,
    'instantSave' => false,
    'rows' => 3,
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

    <flux:textarea
        {{ $attributes->except(['disabled', 'wire:model', 'wire:model.live', 'rows'])->merge([
            'id' => $id,
            'name' => $id,
            'disabled' => $disabled,
            'rows' => $rows,
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
