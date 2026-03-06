@props([
    'href',
    'icon' => null,
    'active' => false,
])

{{-- Simple nav item without admin perm gates or context menus --}}
<li>
    <a {{ $attributes->merge([
        'href' => $href,
        'class' => 'flex items-center gap-2 px-3 py-2 text-sm rounded-lg transition ' .
            ($active
                ? 'bg-violet-50 text-violet-700 dark:bg-violet-900/20 dark:text-violet-400'
                : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800')
    ]) }}>
        @if($icon)
        <core:icon :name="$icon" class="w-5 h-5 {{ $active ? 'text-violet-500' : 'text-gray-400' }}" />
        @endif
        <span>{{ $slot }}</span>
    </a>
</li>
