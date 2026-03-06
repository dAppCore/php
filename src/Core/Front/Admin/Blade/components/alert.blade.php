@php
    // Explicit class mappings for Tailwind JIT purging
    $containerClasses = match($bgColor) {
        'amber' => 'border-amber-500/50 bg-amber-50 dark:bg-amber-900/20',
        'green' => 'border-green-500/50 bg-green-50 dark:bg-green-900/20',
        'red' => 'border-red-500/50 bg-red-50 dark:bg-red-900/20',
        default => 'border-blue-500/50 bg-blue-50 dark:bg-blue-900/20',
    };
    $iconClass = match($bgColor) {
        'amber' => 'text-amber-500',
        'green' => 'text-green-500',
        'red' => 'text-red-500',
        default => 'text-blue-500',
    };
    $titleClass = match($bgColor) {
        'amber' => 'text-amber-700 dark:text-amber-300',
        'green' => 'text-green-700 dark:text-green-300',
        'red' => 'text-red-700 dark:text-red-300',
        default => 'text-blue-700 dark:text-blue-300',
    };
    $messageClass = match($bgColor) {
        'amber' => 'text-amber-600 dark:text-amber-400',
        'green' => 'text-green-600 dark:text-green-400',
        'red' => 'text-red-600 dark:text-red-400',
        default => 'text-blue-600 dark:text-blue-400',
    };
    $dismissClass = match($bgColor) {
        'amber' => 'text-amber-500 hover:text-amber-700',
        'green' => 'text-green-500 hover:text-green-700',
        'red' => 'text-red-500 hover:text-red-700',
        default => 'text-blue-500 hover:text-blue-700',
    };
@endphp
<div {{ $attributes->merge(['class' => "p-4 mb-6 rounded-lg border {$containerClasses}"]) }}>
    <div class="flex items-center gap-3">
        <core:icon :name="$iconName" class="w-5 h-5 {{ $iconClass }} shrink-0" />
        <div class="flex-1">
            @if($title)
                <div class="font-medium {{ $titleClass }}">{{ $title }}</div>
            @endif
            @if($message)
                <div class="text-sm {{ $messageClass }}">{{ $message }}</div>
            @endif
            {{ $slot }}
        </div>
        @if($action)
            @if(isset($action['href']))
                <a href="{{ $action['href'] }}" wire:navigate>
                    <core:button variant="ghost" size="sm">{{ $action['label'] }}</core:button>
                </a>
            @elseif(isset($action['click']))
                <core:button variant="ghost" size="sm" wire:click="{{ $action['click'] }}">{{ $action['label'] }}</core:button>
            @endif
        @endif
        @if($dismissible)
            <button type="button" class="{{ $dismissClass }}" wire:click="$dispatch('dismiss-alert')">
                <core:icon name="x-mark" class="w-4 h-4" />
            </button>
        @endif
    </div>
</div>
