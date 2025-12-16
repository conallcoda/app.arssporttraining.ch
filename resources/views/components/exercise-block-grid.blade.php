@props([
    'block',
    'title' => null,
    'helpText' => null,
    'mergeIdenticalSessions' => true,
    'highlightedCells' => [],
    'titleAction' => null,
    'subtitleContent' => null,
    'headingSize' => 'sm',
    'overrideDisplayMode' => null,
    'exerciseId' => null,
    'targetValue' => null,
    'targetDefault' => null,
    'targetOptions' => [0 => '0%', 2.5 => '2.5%', 5 => '5%', 7.5 => '7.5%', 10 => '10%'],
    'repsValue' => null,
    'repsDefault' => null,
    'repsOptions' => [8 => '8', 10 => '10', 12 => '12', 14 => '14', 16 => '16'],
    'setsValue' => null,
    'setsDefault' => null,
    'setsOptions' => [4 => '4', 5 => '5', 6 => '6'],
    'hasTargetOverride' => false,
    'hasRepsOverride' => false,
    'hasSetsOverride' => false,
])

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

    $isHighlighted = fn($weekIndex, $sessionIndex, $setIndex, $field) => isset(
        $highlightedCells["{$weekIndex}.{$sessionIndex}.{$setIndex}.{$field}"],
    );

    $cellClasses = [
        'reps' => 'bg-blue-50 dark:bg-blue-900/20',
        'weight' => 'bg-green-50 dark:bg-green-900/20',
        'oneRepMax' => 'bg-orange-50 dark:bg-orange-900/20',
    ];

    $getCellClass = fn($weekIndex, $sessionIndex, $setIndex, $field) => $cellClasses[$field] .
        ($isHighlighted($weekIndex, $sessionIndex, $setIndex, $field)
            ? ' !border-2 !border-black dark:!border-white'
            : '');
@endphp

<div class="text-sm min-w-0">
    <div class="mb-2 flex items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <flux:heading :size="$headingSize">{{ $title ?? '' }}</flux:heading>
            @if ($helpText)
                <x-help-tooltip :content="$helpText" position="top" />
            @endif
        </div>
        @if ($titleAction)
            {{ $titleAction }}
        @endif
    </div>
    @if ($overrideDisplayMode === 'radios' && $exerciseId !== null)
        <div class="mb-2 flex flex-col gap-2">
            <div class="flex items-center gap-2"
                wire:key="target-radio-{{ $exerciseId }}-{{ $targetValue }}"
                x-data="{ value: '{{ $targetValue }}' }"
                x-init="$watch('value', v => $wire.updateExerciseOverride({{ $exerciseId }}, 'target', parseFloat(v)))">
                <span class="text-xs w-16 text-zinc-500 dark:text-zinc-400">Target:</span>
                <div class="{{ $hasTargetOverride ? 'override-active' : '' }}">
                    <flux:radio.group x-model="value" variant="segmented" size="sm">
                        @foreach ($targetOptions as $optValue => $optLabel)
                            <flux:radio value="{{ $optValue }}" label="{{ $optLabel }}" />
                        @endforeach
                    </flux:radio.group>
                </div>
            </div>
            <div class="flex items-center gap-2"
                wire:key="reps-radio-{{ $exerciseId }}-{{ $repsValue }}"
                x-data="{ value: '{{ $repsValue }}' }"
                x-init="$watch('value', v => $wire.updateExerciseOverride({{ $exerciseId }}, 'startingReps', parseInt(v)))">
                <span class="text-xs w-16 text-zinc-500 dark:text-zinc-400">Reps:</span>
                <div class="{{ $hasRepsOverride ? 'override-active' : '' }}">
                    <flux:radio.group x-model="value" variant="segmented" size="sm">
                        @foreach ($repsOptions as $optValue => $optLabel)
                            <flux:radio value="{{ $optValue }}" label="{{ $optLabel }}" />
                        @endforeach
                    </flux:radio.group>
                </div>
            </div>
            <div class="flex items-center gap-2"
                wire:key="sets-radio-{{ $exerciseId }}-{{ $setsValue }}"
                x-data="{ value: '{{ $setsValue }}' }"
                x-init="$watch('value', v => $wire.updateExerciseOverride({{ $exerciseId }}, 'sets', parseInt(v)))">
                <span class="text-xs w-16 text-zinc-500 dark:text-zinc-400">Sets:</span>
                <div class="{{ $hasSetsOverride ? 'override-active' : '' }}">
                    <flux:radio.group x-model="value" variant="segmented" size="sm">
                        @foreach ($setsOptions as $optValue => $optLabel)
                            <flux:radio value="{{ $optValue }}" label="{{ $optLabel }}" />
                        @endforeach
                    </flux:radio.group>
                </div>
            </div>
        </div>
    @elseif ($overrideDisplayMode === 'pills' && $exerciseId !== null)
        <div class="mb-2 flex items-center gap-1.5">
            <div wire:key="target-{{ $exerciseId }}-{{ $targetValue }}"
                class="inline-flex items-center rounded-md border px-1.5 py-0.5 {{ $hasTargetOverride ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800' }}"
                x-data="editable_cell($wire, 'updateExerciseOverride', [{{ $exerciseId }}, 'target'], {{ $targetValue }})" @click="startEditing">
                <span class="{{ $hasTargetOverride ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500 dark:text-zinc-400' }} mr-1">Target:</span>
                <span x-show="!editing" class="font-medium cursor-pointer" x-text="value + '%'"></span>
                <input x-show="editing" x-cloak x-ref="input" x-model="value" @blur="save" @keydown="handleKeydown" type="number" step="0.1" min="0"
                    class="w-12 text-center bg-transparent border-none outline-none p-0 text-sm font-medium focus:ring-0" />
            </div>
            <div wire:key="reps-{{ $exerciseId }}-{{ $repsValue }}"
                class="inline-flex items-center rounded-md border px-1.5 py-0.5 {{ $hasRepsOverride ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800' }}"
                x-data="editable_cell($wire, 'updateExerciseOverride', [{{ $exerciseId }}, 'startingReps'], {{ $repsValue }})" @click="startEditing">
                <span class="{{ $hasRepsOverride ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500 dark:text-zinc-400' }} mr-1">Start Reps:</span>
                <span x-show="!editing" class="font-medium cursor-pointer" x-text="value"></span>
                <input x-show="editing" x-cloak x-ref="input" x-model="value" @blur="save" @keydown="handleKeydown" type="number" step="1" min="1" max="20"
                    class="w-8 text-center bg-transparent border-none outline-none p-0 text-sm font-medium focus:ring-0" />
            </div>
            <div wire:key="sets-{{ $exerciseId }}-{{ $setsValue }}"
                class="inline-flex items-center rounded-md border px-1.5 py-0.5 {{ $hasSetsOverride ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800' }}"
                x-data="editable_cell($wire, 'updateExerciseOverride', [{{ $exerciseId }}, 'sets'], {{ $setsValue }})" @click="startEditing">
                <span class="{{ $hasSetsOverride ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500 dark:text-zinc-400' }} mr-1">Sets:</span>
                <span x-show="!editing" class="font-medium cursor-pointer" x-text="value"></span>
                <input x-show="editing" x-cloak x-ref="input" x-model="value" @blur="save" @keydown="handleKeydown" type="number" step="1" min="1" max="6"
                    class="w-8 text-center bg-transparent border-none outline-none p-0 text-sm font-medium focus:ring-0" />
            </div>
        </div>
    @elseif ($subtitleContent)
        <div class="mb-2">
            {{ $subtitleContent }}
        </div>
    @endif
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
                                    <td
                                        class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $getCellClass($weekIndex, 0, $i, 'reps') }}">
                                        {{ $session->sets[$i]->reps ?? '-' }}
                                    </td>
                                @else
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center"
                                        rowspan="3"></td>
                                @endif
                            @endfor
                        </tr>
                        <tr>
                            @for ($i = 0; $i < $setCount; $i++)
                                @if (isset($session->sets[$i]))
                                    <td
                                        class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $getCellClass($weekIndex, 0, $i, 'weight') }}">
                                        {{ $session->sets[$i]->weight !== null ? number_format($session->sets[$i]->weight, 1) : '-' }}
                                    </td>
                                @endif
                            @endfor
                        </tr>
                        <tr>
                            @for ($i = 0; $i < $setCount; $i++)
                                @if (isset($session->sets[$i]))
                                    <td
                                        class="border border-zinc-300 dark:border-zinc-600 px-3 py-1 text-center text-xs text-zinc-500 dark:text-zinc-400 {{ $getCellClass($weekIndex, 0, $i, 'oneRepMax') }}">
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
                                        <td
                                            class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $getCellClass($weekIndex, $sessionIndex, $i, 'reps') }}">
                                            {{ $session->sets[$i]->reps ?? '-' }}
                                        </td>
                                    @else
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center"
                                            rowspan="3"></td>
                                    @endif
                                @endfor
                            </tr>
                            <tr>
                                @for ($i = 0; $i < $setCount; $i++)
                                    @if (isset($session->sets[$i]))
                                        <td
                                            class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $getCellClass($weekIndex, $sessionIndex, $i, 'weight') }}">
                                            {{ $session->sets[$i]->weight !== null ? number_format($session->sets[$i]->weight, 1) : '-' }}
                                        </td>
                                    @endif
                                @endfor
                            </tr>
                            <tr>
                                @for ($i = 0; $i < $setCount; $i++)
                                    @if (isset($session->sets[$i]))
                                        <td
                                            class="border border-zinc-300 dark:border-zinc-600 px-3 py-1 text-center text-xs text-zinc-500 dark:text-zinc-400 {{ $getCellClass($weekIndex, $sessionIndex, $i, 'oneRepMax') }}">
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
