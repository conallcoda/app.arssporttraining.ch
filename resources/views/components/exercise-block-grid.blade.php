@props(['block', 'title' => null, 'helpText' => null, 'mergeIdenticalSessions' => true, 'highlightedCells' => [], 'titleAction' => null])

@php

    $weeks = $block->weeks;
    $setCount = 0;
    foreach ($weeks as $week) {
        foreach ($week->sessions as $session) {
            $setCount = max($setCount, count($session->sets));
        }
    }
    $setCount = $setCount ?: 4;

    $sessionsAreIdentical = function ($sessions) {
        if (count($sessions) < 2) {
            return false;
        }
        $firstSessionSets = array_map(fn($set) => $set->toArray(), $sessions[0]->sets);
        foreach (array_slice($sessions, 1) as $session) {
            $sessionSets = array_map(fn($set) => $set->toArray(), $session->sets);
            if ($firstSessionSets !== $sessionSets) {
                return false;
            }
        }
        return true;
    };

    $isHighlighted = fn($weekIndex, $sessionIndex, $setIndex, $field) =>
        isset($highlightedCells["{$weekIndex}.{$sessionIndex}.{$setIndex}.{$field}"]);

    $cellClasses = [
        'reps' => 'bg-blue-50 dark:bg-blue-900/20',
        'weight' => 'bg-green-50 dark:bg-green-900/20',
        'oneRepMax' => 'bg-orange-50 dark:bg-orange-900/20',
    ];

    $getCellClass = fn($weekIndex, $sessionIndex, $setIndex, $field) =>
        $cellClasses[$field] . ($isHighlighted($weekIndex, $sessionIndex, $setIndex, $field) ? ' !border-2 !border-black dark:!border-white' : '');
@endphp

<div class="text-sm min-w-0">
    <div class="mb-2 flex items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <flux:heading size="sm">{{ $title ?? '' }}</flux:heading>
            @if ($helpText)
                <x-help-tooltip :content="$helpText" position="top" />
            @endif
        </div>
        @if ($titleAction)
            {{ $titleAction }}
        @endif
    </div>
    <div class="overflow-x-auto">
    <table class="border-collapse border border-zinc-300 dark:border-zinc-600">
        <thead>
            <tr class="bg-zinc-100 dark:bg-zinc-800">
                <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2">Week</th>
                <th class="border border-zinc-300 dark:border-zinc-600 px-2 py-2">Sessions</th>
                @for ($i = 0; $i < $setCount; $i++)
                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2">Set {{ $i + 1 }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach ($weeks as $weekIndex => $week)
                @php
                    $sessionCount = count($week->sessions);
                    $merged = $mergeIdenticalSessions && $sessionsAreIdentical($week->sessions);
                @endphp
                @if ($merged)
                    @php $session = $week->sessions[0]; @endphp
                    <tr>
                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle"
                            rowspan="3">
                            W{{ $weekIndex + 1 }}
                        </td>
                        <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center bg-zinc-100 dark:bg-zinc-700/50 text-xs font-medium"
                            rowspan="3">
                            1-{{ $sessionCount }}
                        </td>
                        @for ($i = 0; $i < $setCount; $i++)
                            @if (isset($session->sets[$i]))
                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $getCellClass($weekIndex, 0, $i, 'reps') }}">
                                    {{ $session->sets[$i]->reps ?? '-' }}
                                </td>
                            @else
                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center" rowspan="3"></td>
                            @endif
                        @endfor
                    </tr>
                    <tr>
                        @for ($i = 0; $i < $setCount; $i++)
                            @if (isset($session->sets[$i]))
                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $getCellClass($weekIndex, 0, $i, 'weight') }}">
                                    {{ $session->sets[$i]->weight !== null ? number_format($session->sets[$i]->weight, 1) : '-' }}
                                </td>
                            @endif
                        @endfor
                    </tr>
                    <tr>
                        @for ($i = 0; $i < $setCount; $i++)
                            @if (isset($session->sets[$i]))
                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-1 text-center text-xs text-zinc-500 dark:text-zinc-400 {{ $getCellClass($weekIndex, 0, $i, 'oneRepMax') }}">
                                    {{ $session->sets[$i]->oneRepMax !== null ? number_format($session->sets[$i]->oneRepMax, 1) : '-' }}
                                </td>
                            @endif
                        @endfor
                    </tr>
                @else
                    @foreach ($week->sessions as $sessionIndex => $session)
                        @php $isFirstSession = $sessionIndex === 0; @endphp
                        <tr>
                            @if ($isFirstSession)
                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle"
                                    rowspan="{{ $sessionCount * 3 }}">
                                    W{{ $weekIndex + 1 }}
                                </td>
                            @endif
                            <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center bg-zinc-100 dark:bg-zinc-700/50 text-xs font-medium"
                                rowspan="3">
                                S{{ $sessionIndex + 1 }}
                            </td>
                            @for ($i = 0; $i < $setCount; $i++)
                                @if (isset($session->sets[$i]))
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $getCellClass($weekIndex, $sessionIndex, $i, 'reps') }}">
                                        {{ $session->sets[$i]->reps ?? '-' }}
                                    </td>
                                @else
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center" rowspan="3"></td>
                                @endif
                            @endfor
                        </tr>
                        <tr>
                            @for ($i = 0; $i < $setCount; $i++)
                                @if (isset($session->sets[$i]))
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $getCellClass($weekIndex, $sessionIndex, $i, 'weight') }}">
                                        {{ $session->sets[$i]->weight !== null ? number_format($session->sets[$i]->weight, 1) : '-' }}
                                    </td>
                                @endif
                            @endfor
                        </tr>
                        <tr>
                            @for ($i = 0; $i < $setCount; $i++)
                                @if (isset($session->sets[$i]))
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-1 text-center text-xs text-zinc-500 dark:text-zinc-400 {{ $getCellClass($weekIndex, $sessionIndex, $i, 'oneRepMax') }}">
                                        {{ $session->sets[$i]->oneRepMax !== null ? number_format($session->sets[$i]->oneRepMax, 1) : '-' }}
                                    </td>
                                @endif
                            @endfor
                        </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>
    </div>
</div>
