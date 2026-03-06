{{-- Core Pillbox - Flux Pro component. Props: placeholder, label, description, size, searchable, disabled, invalid, multiple --}}
@php(\Core\Pro::requireFluxPro('core:pillbox'))
<flux:pillbox {{ $attributes }}>{{ $slot }}</flux:pillbox>
