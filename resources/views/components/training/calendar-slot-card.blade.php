@props([
    'as' => 'button',
    'type' => 'button',
    'color' => null,
    'colorExpr' => null,
    'time' => null,
    'timeExpr' => null,
    'name' => null,
    'nameExpr' => null,
    'subtitle' => null,
    'subtitleExpr' => null,
    'statusColor' => null,
    'statusColorExpr' => null,
    'compact' => false,
])

@php
    $hasStatusBar = $statusColor !== null || $statusColorExpr !== null;
    $statusBarPadding = $hasStatusBar ? ($compact ? 'pb-1.5' : 'pb-2.5') : '';
    $baseClasses = $compact
        ? 'flex flex-col px-1.5 py-0.5 rounded text-left transition-opacity'
        : 'flex flex-col px-2 py-1.5 rounded-lg text-left transition-opacity';
    $baseClasses .= $statusBarPadding !== '' ? ' '.$statusBarPadding : '';
    $interactiveClasses = 'cursor-pointer hover:opacity-80';
    $neutralClasses = 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400';
    $nameClasses = $compact ? 'text-[11px] truncate' : 'text-xs font-medium truncate';
    $mutedStaticClasses = $color ? 'opacity-80' : 'opacity-60';
    $colorStyle = $color ? \Coda\Cms\Support\ColorPalette::solid($color) : '';
    $statusStyle = $statusColor
        ? "--status-bar-light: {$statusColor['light']}; --status-bar-dark: {$statusColor['dark']};"
        : '';
    $staticStyle = trim($colorStyle.' '.$statusStyle);
    $dynamicOuterClassExpr = $colorExpr ? "{$colorExpr} ? '' : '{$neutralClasses}'" : null;
    $dynamicStyleParts = [];
    if ($colorExpr) {
        $dynamicStyleParts[] = "...({$colorExpr} ? { backgroundColor: 'var(--color-' + {$colorExpr} + '-500)', color: 'white' } : {})";
    }
    if ($statusColorExpr) {
        $dynamicStyleParts[] = "...({$statusColorExpr} ? { '--status-bar-light': {$statusColorExpr}.light, '--status-bar-dark': {$statusColorExpr}.dark } : {})";
    }
    $dynamicStyleExpr = ! empty($dynamicStyleParts) ? '({ '.implode(', ', $dynamicStyleParts).' })' : null;
    $dynamicMutedClassExpr = $colorExpr ? "{$colorExpr} ? 'opacity-80' : 'opacity-60'" : null;
    $tag = $as;
@endphp

<{{ $tag }}
    @if ($tag === 'button') type="{{ $type }}" @endif
    {{ $attributes->class([$baseClasses, $interactiveClasses, 'status-bar' => $hasStatusBar, $neutralClasses => ! $color && ! $colorExpr]) }}
    @if ($staticStyle !== '') style="{{ $staticStyle }}" @endif
    @if ($dynamicOuterClassExpr) :class="{{ $dynamicOuterClassExpr }}" @endif
    @if ($dynamicStyleExpr) :style="{{ $dynamicStyleExpr }}" @endif
>
    @if ($time !== null || $timeExpr !== null)
        <span
            class="text-[10px] {{ $timeExpr ? '' : $mutedStaticClasses }}"
            @if ($timeExpr) x-text="{{ $timeExpr }}" @endif
            @if ($timeExpr && $dynamicMutedClassExpr) :class="{{ $dynamicMutedClassExpr }}" @endif
        >{{ $timeExpr ? '' : $time }}</span>
    @endif

    @if ($name !== null || $nameExpr !== null)
        <span
            class="{{ $nameClasses }}"
            @if ($nameExpr) x-text="{{ $nameExpr }}" @endif
        >{{ $nameExpr ? '' : $name }}</span>
    @endif

    @if ($subtitle !== null || $subtitleExpr !== null)
        <span
            class="text-[10px] truncate {{ $subtitleExpr ? '' : $mutedStaticClasses }}"
            @if ($subtitleExpr) x-text="{{ $subtitleExpr }}" @endif
            @if ($subtitleExpr && $dynamicMutedClassExpr) :class="{{ $dynamicMutedClassExpr }}" @endif
        >{{ $subtitleExpr ? '' : $subtitle }}</span>
    @endif
</{{ $tag }}>
