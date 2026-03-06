<div>
    @if($label)
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
    @endif
    <select
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100']) }}
        {!! $wireModel !!}
    >
        <option value="">{{ $placeholderText }}</option>
        @foreach($normalizedOptions as $option)
            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
        @endforeach
    </select>
</div>
