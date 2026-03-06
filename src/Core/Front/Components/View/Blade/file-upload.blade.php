{{-- Core File Upload - Flux Pro component. Props: name, multiple, label, description, error, disabled --}}
@php(\Core\Pro::requireFluxPro('core:file-upload'))
<flux:file-upload {{ $attributes }}>{{ $slot }}</flux:file-upload>
