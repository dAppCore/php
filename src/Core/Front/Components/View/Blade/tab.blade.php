{{-- Core Tab - Thin wrapper around flux:tab with Font Awesome icon support --}}
@props([
    'icon' => null,
])

<flux:tab {{ $attributes->except('icon') }}>
    @if($icon)
        <core:icon :name="$icon" class="size-5" />
    @endif
    {{ $slot }}
</flux:tab>
