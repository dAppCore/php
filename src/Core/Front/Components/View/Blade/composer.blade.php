{{-- Core Composer - Flux Pro component. Props: name, placeholder, label, rows, submit, disabled, invalid --}}
@php(\Core\Pro::requireFluxPro('core:composer'))
<flux:composer {{ $attributes }}>{{ $slot }}</flux:composer>
