@props(['name'])

@php
    // Auto-detect selected state from parent tabs component
    $selected = \Core\Front\Admin\TabContext::$selected === $name;
@endphp

<core:tab.panel name="{{ $name }}" :selected="$selected" {{ $attributes }}>
    {{ $slot }}
</core:tab.panel>
