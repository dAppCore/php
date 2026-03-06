@props([
    'minLength' => null,         // min-length
])

<flux:pillbox.option.create {{ $attributes }}>
    {{ $slot }}
</flux:pillbox.option.create>
