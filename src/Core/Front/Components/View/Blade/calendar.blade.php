{{-- Core Calendar - Flux Pro component. Props: value, mode, min, max, size, months, navigation, static, multiple, locale --}}
@php(\Core\Pro::requireFluxPro('core:calendar'))
<flux:calendar {{ $attributes }}>{{ $slot }}</flux:calendar>
