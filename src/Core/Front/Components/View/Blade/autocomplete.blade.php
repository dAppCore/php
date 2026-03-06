{{-- Core Autocomplete - Flux Pro component. Props: label, description, placeholder, size, disabled, invalid, clearable --}}
@php(\Core\Pro::requireFluxPro('core:autocomplete'))
<flux:autocomplete {{ $attributes }}>{{ $slot }}</flux:autocomplete>
