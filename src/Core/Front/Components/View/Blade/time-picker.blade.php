{{-- Core Time Picker - Flux Pro component. Props: value, type, interval, min, max, label, placeholder, size, clearable, disabled, locale --}}
@php(\Core\Pro::requireFluxPro('core:time-picker'))
<flux:time-picker {{ $attributes }}>{{ $slot }}</flux:time-picker>
