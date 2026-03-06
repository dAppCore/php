@props(['variant' => 'HCF'])

@php
    $has = fn($slot) => str_contains($variant, $slot);
@endphp

<div {{ $attributes->merge(['class' => 'hlcrf-layout']) }}>
    @if($has('H') && isset($header))
        <header class="hlcrf-header">
            {{ $header }}
        </header>
    @endif

    @if($has('L') || $has('C') || $has('R'))
        <div class="hlcrf-body flex flex-1">
            @if($has('L') && isset($left))
                <aside class="hlcrf-left shrink-0">
                    {{ $left }}
                </aside>
            @endif

            @if($has('C'))
                <main class="hlcrf-content flex-1">
                    {{ $slot }}
                </main>
            @endif

            @if($has('R') && isset($right))
                <aside class="hlcrf-right shrink-0">
                    {{ $right }}
                </aside>
            @endif
        </div>
    @endif

    @if($has('F') && isset($footer))
        <footer class="hlcrf-footer">
            {{ $footer }}
        </footer>
    @endif
</div>
