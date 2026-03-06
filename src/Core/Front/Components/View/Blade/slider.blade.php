{{-- Core Slider - Flux Pro component. Props: range, min, max, step, big-step, min-steps-between --}}
@php(\Core\Pro::requireFluxPro('core:slider'))
<flux:slider {{ $attributes }}>{{ $slot }}</flux:slider>
