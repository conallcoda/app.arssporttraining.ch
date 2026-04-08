@props([
    'score' => null,
    'label' => null,
    'color' => null,
    'size' => 56,
    'showLabel' => true,
    'editable' => false,
])

@php
    $percent = $score !== null ? $score / 4 : 0;
    $colorMap = [
        'green' => '#22c55e',
        'amber' => '#f59e0b',
        'orange' => '#f97316',
        'red' => '#ef4444',
    ];
    $fillColor = $colorMap[$color] ?? '#a1a1aa';
    $half = $size / 2;
    $strokeWidth = $size > 48 ? 5 : 4;
    $r = $half - ($strokeWidth / 2) - 2;
    $circumference = 2 * M_PI * $r;
    $offset = $circumference * (1 - $percent);
    $scoreFontSize = round($size * 0.28);
    $naFontSize = round($size * 0.25);
@endphp

<div {{ $attributes->class('flex flex-col items-center gap-1') }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}">
        <circle cx="{{ $half }}" cy="{{ $half }}" r="{{ $r }}" class="fill-none stroke-zinc-200 dark:stroke-zinc-600" stroke-width="{{ $strokeWidth }}" />
        @if ($score !== null)
            <circle
                cx="{{ $half }}" cy="{{ $half }}" r="{{ $r }}"
                fill="none"
                stroke="{{ $fillColor }}"
                stroke-width="{{ $strokeWidth }}"
                stroke-linecap="round"
                stroke-dasharray="{{ $circumference }}"
                stroke-dashoffset="{{ $offset }}"
                transform="rotate(-90 {{ $half }} {{ $half }})"
            />
        @endif
        @if ($score !== null)
            <text x="{{ $half }}" y="{{ $half }}" text-anchor="middle" dominant-baseline="central" class="fill-zinc-900 dark:fill-white" font-size="{{ $scoreFontSize }}" font-weight="600">{{ $score }}/4</text>
        @else
            <text x="{{ $half }}" y="{{ $half }}" text-anchor="middle" dominant-baseline="central" class="fill-zinc-400" font-size="{{ $naFontSize }}" font-weight="500">N/A</text>
        @endif
    </svg>
    @if ($showLabel && $score !== null)
        <span class="text-xs font-medium" style="color: {{ $fillColor }}">{{ $label }}</span>
    @endif
    @if ($editable)
        <flux:button variant="primary" size="xs" wire:click="$dispatch('open-readiness-modal')">
            {{ $score === null ? 'Check In' : 'Change' }}
        </flux:button>
    @endif
</div>
