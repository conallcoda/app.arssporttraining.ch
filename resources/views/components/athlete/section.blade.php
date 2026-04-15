@props([
    'mobileShade' => null,
    'row' => null,
    'contentClass' => '',
])

@php
    $resolvedMobileShade = $mobileShade;

    if ($resolvedMobileShade === null) {
        $resolvedMobileShade = is_numeric($row) && ((int) $row % 2) === 1
            ? 'alt'
            : 'base';
    }

    $surfaceClasses = match ($resolvedMobileShade) {
        'alt' => 'bg-zinc-950/[0.02] dark:bg-white/[0.02]',
        'none' => '',
        default => 'bg-zinc-950/[0.04] dark:bg-white/[0.04]',
    };
@endphp

<div {{ $attributes->class([
    '-mx-2 px-2 py-3 sm:mx-0 sm:rounded-3xl sm:border sm:border-zinc-200 sm:px-5 sm:py-5 sm:shadow-sm dark:sm:border-zinc-700',
    $surfaceClasses,
]) }}>
    <div @class([$contentClass])>
        {{ $slot }}
    </div>
</div>
