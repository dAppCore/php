{{-- Core Date Picker - Flux Pro component. Props: value, mode, min, max, months, label, placeholder, size, clearable, disabled, locale --}}
@php(\Core\Pro::requireFluxPro('core:date-picker'))
<flux:date-picker {{ $attributes }}>{{ $slot }}</flux:date-picker>
