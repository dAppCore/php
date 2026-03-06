{{-- Core Editor - Flux Pro component. Props: value, label, description, placeholder, toolbar, disabled, invalid --}}
@php(\Core\Pro::requireFluxPro('core:editor'))
<flux:editor {{ $attributes }}>{{ $slot }}</flux:editor>
