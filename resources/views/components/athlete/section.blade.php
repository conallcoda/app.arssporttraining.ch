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
    'w-full mx-0 px-3 md:px-4 py-3',
    $surfaceClasses,
]) }}>
    <div @class([$contentClass])>
        {{ $slot }}
    </div>
</div>
