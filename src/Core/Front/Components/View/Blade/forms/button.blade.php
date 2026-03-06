{{--
Authorization-aware button component.

Wraps flux:button with built-in authorization checking.

Usage:
<x-forms.button>Save</x-forms.button>
<x-forms.button variant="primary">Save</x-forms.button>
<x-forms.button canGate="update" :canResource="$page">Save</x-forms.button>
--}}

@props([
    'canGate' => null,
    'canResource' => null,
    'variant' => 'primary',
    'type' => 'submit',
])

@php
    $disabled = $attributes->get('disabled', false);
    if ($canGate && $canResource && !$disabled) {
        $disabled = !auth()->user()?->can($canGate, $canResource);
    }
@endphp

<flux:button
    {{ $attributes->except(['disabled', 'variant', 'type'])->merge([
        'type' => $type,
        'variant' => $variant,
        'disabled' => $disabled,
    ]) }}
>
    {{ $slot }}
</flux:button>
