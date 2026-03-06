{{-- Core Command - Flux Pro component --}}
@php(\Core\Pro::requireFluxPro('core:command'))
<flux:command {{ $attributes }}>{{ $slot }}</flux:command>
