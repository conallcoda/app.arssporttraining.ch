@props(['block', 'title' => null, 'mergeIdenticalSessions' => true])

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
@endphp

<div class="text-sm">
    <div class="mb-2">
        <flux:heading size="sm">{{ $title ?? '' }}</flux:heading>
    </div>
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
                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center bg-blue-50 dark:bg-blue-900/20">
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
                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center bg-green-50 dark:bg-green-900/20">
                                    {{ $session->sets[$i]->weight !== null ? number_format($session->sets[$i]->weight, 1) : '-' }}
                                </td>
                            @endif
                        @endfor
                    </tr>
                    <tr>
                        @for ($i = 0; $i < $setCount; $i++)
                            @if (isset($session->sets[$i]))
                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-1 text-center text-xs text-zinc-500 dark:text-zinc-400 bg-orange-50 dark:bg-orange-900/20">
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
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center bg-blue-50 dark:bg-blue-900/20">
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
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center bg-green-50 dark:bg-green-900/20">
                                        {{ $session->sets[$i]->weight !== null ? number_format($session->sets[$i]->weight, 1) : '-' }}
                                    </td>
                                @endif
                            @endfor
                        </tr>
                        <tr>
                            @for ($i = 0; $i < $setCount; $i++)
                                @if (isset($session->sets[$i]))
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-1 text-center text-xs text-zinc-500 dark:text-zinc-400 bg-orange-50 dark:bg-orange-900/20">
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
