@props([
    'title',
    'description' => null,
    'subtitle' => null,
])

@php
    $text = $description ?? $subtitle;
@endphp

<div class="sm:flex sm:justify-between sm:items-center mb-8">
    {{-- Left: Title and description --}}
    <div class="mb-4 sm:mb-0">
        <flux:heading size="xl" level="1">{{ $title }}</flux:heading>
        @if($text)
            <flux:text class="mt-1 text-gray-500 dark:text-gray-400">{{ $text }}</flux:text>
        @endif
    </div>

    {{-- Right: Actions slot --}}
    @if($slot->isNotEmpty())
        <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
