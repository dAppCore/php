{{-- Core Chart - Flux Pro component. Props: value, curve (smooth|none) --}}
@php(\Core\Pro::requireFluxPro('core:chart'))
<flux:chart {{ $attributes }}>{{ $slot }}</flux:chart>
