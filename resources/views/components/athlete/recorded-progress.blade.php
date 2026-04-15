@props([
    'segments' => [],
    'labelAlign' => 'left',
    'class' => '',
])

@php
    $segments = collect($segments)->values()->all();
    $recordedCount = count(array_filter($segments, fn (array $segment): bool => (bool) ($segment['submitted'] ?? false)));
    $totalCount = count($segments);
@endphp

<div {{ $attributes->class(['space-y-1', $class]) }}>
    <div class="relative h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
        <div class="absolute inset-0 flex">
            @foreach ($segments as $segment)
                <div
                    class="flex-1 transition-colors duration-300 {{ ($segment['submitted'] ?? false) ? 'status-fill' : '' }}"
                    @if ($segment['submitted'] ?? false)
                        style="--status-bar-light: {{ $segment['color']['light'] ?? '228 228 231' }}; --status-bar-dark: {{ $segment['color']['dark'] ?? '161 161 170' }};"
                    @endif
                ></div>
            @endforeach
        </div>
    </div>

    <div @class([
        'text-[11px] font-medium text-zinc-600 dark:text-zinc-300',
        'text-center' => $labelAlign === 'center',
    ])>
        {{ $recordedCount }}/{{ $totalCount }} recorded
    </div>
</div>
