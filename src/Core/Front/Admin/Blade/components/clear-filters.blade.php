<div class="flex items-end">
    <button
        type="button"
        wire:click="{{ $clearStatements }}"
        {{ $attributes->merge(['class' => 'w-full rounded-lg bg-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600']) }}
    >
        <core:icon name="x-mark" class="mr-2 inline size-4" />
        {{ $label }}
    </button>
</div>
