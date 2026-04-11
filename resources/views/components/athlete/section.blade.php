@props([
    'mobileShade' => 'base',
    'contentClass' => '',
])

@php
    $surfaceClasses = match ($mobileShade) {
        'alt' => 'bg-zinc-950/[0.02] dark:bg-white/[0.02]',
        'none' => '',
        default => 'bg-zinc-950/[0.04] dark:bg-white/[0.04]',
    };
@endphp

<div {{ $attributes->class([
    '-mx-2 px-2 py-3 sm:mx-0 sm:rounded-3xl sm:border sm:border-zinc-200 sm:px-5 sm:py-5 sm:shadow-sm dark:sm:border-zinc-700',
    $surfaceClasses,
]) }}>
    <div @class(['space-y-3 sm:space-y-4', $contentClass])>
        {{ $slot }}
    </div>
</div>
