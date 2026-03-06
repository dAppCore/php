<div {{ $attributes->merge(['class' => "mb-6 grid grid-cols-1 gap-4 {$gridCols}"]) }}>
    {{ $slot }}
</div>
