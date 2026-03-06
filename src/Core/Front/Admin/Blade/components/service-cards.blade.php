@props([
    'items' => [],
])

<div {{ $attributes->merge(['class' => 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-8']) }}>
    @foreach($items as $service)
        <admin:service-card :service="$service" />
    @endforeach
</div>
