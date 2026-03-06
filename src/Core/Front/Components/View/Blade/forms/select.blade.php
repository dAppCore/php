{{--
Authorization-aware select component.

Wraps flux:select with built-in authorization checking.

Usage:
<x-forms.select id="theme" wire:model="theme" label="Theme">
    <option value="light">Light</option>
    <option value="dark">Dark</option>
</x-forms.select>

<x-forms.select id="theme" wire:model="theme" label="Theme" canGate="update" :canResource="$page">
    <flux:select.option value="light">Light</flux:select.option>
    <flux:select.option value="dark">Dark</flux:select.option>
</x-forms.select>
--}}

@props([
    'id',
    'label' => null,
    'helper' => null,
    'canGate' => null,
    'canResource' => null,
    'instantSave' => false,
    'placeholder' => null,
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

    <flux:select
        {{ $attributes->except(['disabled', 'wire:model', 'wire:model.live', 'placeholder'])->merge([
            'id' => $id,
            'name' => $id,
            'disabled' => $disabled,
            'placeholder' => $placeholder,
        ]) }}
        @if($wireModel)
            @if($instantSave)
                wire:model.live="{{ $wireModel }}"
            @else
                wire:model="{{ $wireModel }}"
            @endif
        @endif
    >
        {{ $slot }}
    </flux:select>

    @if($helper)
        <flux:description>{{ $helper }}</flux:description>
    @endif

    <flux:error name="{{ $id }}" />
</flux:field>
