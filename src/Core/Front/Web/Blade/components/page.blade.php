@props([
    'title' => null,
    'description' => null,
])

{{-- Simple page wrapper for public web pages --}}
<div {{ $attributes->merge(['class' => 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8']) }}>
    @if($title)
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $title }}</h1>
        @if($description)
        <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $description }}</p>
        @endif
    </div>
    @endif

    {{ $slot }}
</div>
