@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes }}>
    <admin:page-header :title="$title" :subtitle="$subtitle">
        {{ $actions ?? '' }}
    </admin:page-header>

    {{ $slot }}
</div>
