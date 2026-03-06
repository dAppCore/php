{{-- Core Kanban - Flux Pro component --}}
@php(\Core\Pro::requireFluxPro('core:kanban'))
<flux:kanban {{ $attributes }}>{{ $slot }}</flux:kanban>
