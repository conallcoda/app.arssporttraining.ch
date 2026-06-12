@props([
    'class' => 'w-6',
])

@php
    $cmsName = config('cms.name') ?? config('app.name', 'CMS');
    $cmsLogo = config('cms.logo');
    $lightLogo = is_array($cmsLogo) ? ($cmsLogo['light'] ?? $cmsLogo['dark'] ?? null) : $cmsLogo;
    $darkLogo = is_array($cmsLogo) ? ($cmsLogo['dark'] ?? $cmsLogo['light'] ?? null) : $cmsLogo;
@endphp

@if ($lightLogo || $darkLogo)
    <img
        x-data
        x-show="! $flux.dark"
        src="{{ asset($lightLogo ?? $darkLogo ?? '') }}"
        alt="{{ $cmsName }}"
        {{ $attributes->class($class) }}
    />
    <img
        x-data
        x-show="$flux.dark"
        src="{{ asset($darkLogo ?? $lightLogo ?? '') }}"
        alt="{{ $cmsName }}"
        {{ $attributes->class($class) }}
    />
@endif
