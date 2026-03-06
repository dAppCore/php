{{-- Core Context Menu - Flux Pro component. Props: position, gap, offset, target, disabled --}}
@php(\Core\Pro::requireFluxPro('core:context'))
<flux:context {{ $attributes }}>{{ $slot }}</flux:context>
