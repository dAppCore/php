<div class="relative">
    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
        <core:icon name="magnifying-glass" class="size-5 text-gray-400" />
    </div>
    <input
        type="text"
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-3 text-gray-900 placeholder-gray-500 focus:border-violet-500 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400']) }}
        placeholder="{{ $placeholder }}"
        {!! $wireModel !!}
    />
</div>
