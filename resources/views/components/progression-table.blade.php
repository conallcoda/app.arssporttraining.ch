@props(['title', 'rows' => [], 'setCount' => 4, 'exerciseId' => null, 'progression' => null, 'groupByWeek' => false])

@php
    $sourceColors = [
        'computed' => '',
        'manual' => 'bg-yellow-50 dark:bg-yellow-900/20',
        'locked' => 'bg-purple-50 dark:bg-purple-900/20',
    ];

    $groupedRows = [];
    if ($groupByWeek) {
        foreach ($rows as $row) {
            $weekIndex = $row['weekIndex'] ?? 0;
            if (!isset($groupedRows[$weekIndex])) {
                $groupedRows[$weekIndex] = [
                    'week' => $row['week'],
                    'weekIndex' => $weekIndex,
                    'weekUuid' => $row['weekUuid'] ?? null,
                    'projected1RM' => $row['projected1RM'] ?? null,
                    'derived1RM' => $row['derived1RM'] ?? null,
                    'sessions' => [],
                ];
            }
            $groupedRows[$weekIndex]['sessions'][] = $row;
        }
    }
@endphp

<div class="text-sm outline-none"
    x-data="progression_grid"
    tabindex="0"
    @click.outside="clearSelection()"
    @keydown.ctrl.c.prevent="copy()"
    @keydown.meta.c.prevent="copy()"
    @keydown.ctrl.v.prevent="paste()"
    @keydown.meta.v.prevent="paste()"
    @keydown.escape="cancelEdit()"
    @keydown.enter="commitEdit()">
    <div class="mb-2">
        <flux:heading size="sm">{{ $title }}</flux:heading>
    </div>
    <table class="border-collapse border border-zinc-300 dark:border-zinc-600">
        <thead>
            <tr class="bg-zinc-100 dark:bg-zinc-800">
                @if ($groupByWeek)
                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2">Wk</th>
                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-xs" title="Projected 1RM">P.1RM</th>
                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-xs" title="Derived 1RM">D.1RM</th>
                    <th class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 text-xs">Ses</th>
                @else
                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2">W.S</th>
                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-xs" title="Projected 1RM">P.1RM</th>
                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-xs" title="Derived 1RM">D.1RM</th>
                @endif
                @for ($i = 0; $i < $setCount; $i++)
                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2">Set {{ $i + 1 }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @if ($groupByWeek)
                @php $globalRowIndex = 0; @endphp
                @foreach ($groupedRows as $weekData)
                    @php $sessionCount = count($weekData['sessions']); @endphp
                    @foreach ($weekData['sessions'] as $sessionIndex => $row)
                        @php
                            $repsRowIndex = $globalRowIndex * 2;
                            $weightsRowIndex = $globalRowIndex * 2 + 1;
                            $isFirstSession = $sessionIndex === 0;
                            $rowspanForWeek = $sessionCount * 2;
                            $cellMeta = [
                                'exerciseId' => $exerciseId,
                                'weekIndex' => $row['weekIndex'] ?? 0,
                                'weekUuid' => $row['weekUuid'] ?? null,
                                'sessionUuid' => $row['sessionUuid'] ?? null,
                                'sessionDay' => $row['sessionDay'] ?? null,
                                'sessionSlot' => $row['sessionSlot'] ?? null,
                            ];
                        @endphp
                        <tr class="bg-blue-50 dark:bg-blue-900/20">
                            @if ($isFirstSession)
                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle" rowspan="{{ $rowspanForWeek }}">
                                    W{{ $weekData['week'] }}
                                </td>
                                <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 text-center bg-zinc-50 dark:bg-zinc-800/50 text-xs align-middle" rowspan="{{ $rowspanForWeek }}">
                                    @if (isset($weekData['projected1RM']) && $weekData['projected1RM'] !== null)
                                        {{ number_format($weekData['projected1RM'], 1) }}
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </td>
                                <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 text-center bg-zinc-50 dark:bg-zinc-800/50 text-xs align-middle" rowspan="{{ $rowspanForWeek }}">
                                    @if (isset($weekData['derived1RM']) && $weekData['derived1RM'] !== null)
                                        {{ number_format($weekData['derived1RM'], 1) }}
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </td>
                            @endif
                            <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center bg-zinc-100 dark:bg-zinc-700/50 text-xs font-medium" rowspan="2">
                                S{{ $row['session'] }}
                            </td>
                            @foreach ($row['reps'] as $colIndex => $rep)
                                @php
                                    $source = $row['sources'][$colIndex] ?? 'computed';
                                    $sourceClass = $sourceColors[$source] ?? '';
                                    $repsMeta = array_merge($cellMeta, ['field' => 'reps', 'setIndex' => $colIndex]);
                                    $repsMetaJson = json_encode($repsMeta);
                                @endphp
                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center cursor-pointer select-none {{ $sourceClass }}"
                                    :class="getBorderClasses({{ $repsRowIndex }}, {{ $colIndex }})"
                                    @mousedown="handleCellMousedown({{ $repsRowIndex }}, {{ $colIndex }}, $event)"
                                    @dblclick="handleCellDblClick({{ $repsRowIndex }}, {{ $colIndex }}, $event)"
                                    @mouseenter="extendSelection({{ $repsRowIndex }}, {{ $colIndex }}, $event)"
                                    x-init="registerCell({{ $repsRowIndex }}, {{ $colIndex }}, $el, {{ $repsMetaJson }})">
                                    {{ $rep }}
                                    @if ($source === 'manual')
                                        <span class="text-yellow-600 text-xs">*</span>
                                    @elseif ($source === 'locked')
                                        <span class="text-purple-600 text-xs">🔒</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        <tr class="bg-green-50 dark:bg-green-900/20">
                            @foreach ($row['weights'] as $colIndex => $weight)
                                @php
                                    $source = $row['sources'][$colIndex] ?? 'computed';
                                    $sourceClass = $source === 'manual' ? 'bg-yellow-100 dark:bg-yellow-900/30' : ($source === 'locked' ? 'bg-purple-100 dark:bg-purple-900/30' : '');
                                    $weightsMeta = array_merge($cellMeta, ['field' => 'weight', 'setIndex' => $colIndex]);
                                    $weightsMetaJson = json_encode($weightsMeta);
                                @endphp
                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center cursor-pointer select-none {{ $sourceClass }}"
                                    :class="getBorderClasses({{ $weightsRowIndex }}, {{ $colIndex }})"
                                    @mousedown="handleCellMousedown({{ $weightsRowIndex }}, {{ $colIndex }}, $event)"
                                    @dblclick="handleCellDblClick({{ $weightsRowIndex }}, {{ $colIndex }}, $event)"
                                    @mouseenter="extendSelection({{ $weightsRowIndex }}, {{ $colIndex }}, $event)"
                                    x-init="registerCell({{ $weightsRowIndex }}, {{ $colIndex }}, $el, {{ $weightsMetaJson }})">
                                    {{ number_format($weight, 1) }}
                                </td>
                            @endforeach
                        </tr>
                        @php $globalRowIndex++; @endphp
                    @endforeach
                @endforeach
            @else
                @foreach ($rows as $rowIndex => $row)
                    @php
                        $repsRowIndex = $rowIndex * 2;
                        $weightsRowIndex = $rowIndex * 2 + 1;
                        $cellMeta = [
                            'exerciseId' => $exerciseId,
                            'weekIndex' => $row['weekIndex'] ?? $rowIndex,
                            'weekUuid' => $row['weekUuid'] ?? null,
                            'sessionUuid' => $row['sessionUuid'] ?? null,
                            'sessionDay' => $row['sessionDay'] ?? null,
                            'sessionSlot' => $row['sessionSlot'] ?? null,
                        ];
                    @endphp
                    <tr class="bg-blue-50 dark:bg-blue-900/20">
                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50" rowspan="2">
                            {{ $row['week'] }}.{{ $row['session'] }}
                        </td>
                        <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 text-center bg-zinc-50 dark:bg-zinc-800/50 text-xs" rowspan="2">
                            @if (isset($row['projected1RM']) && $row['projected1RM'] !== null)
                                {{ number_format($row['projected1RM'], 1) }}
                            @else
                                <span class="text-zinc-400">-</span>
                            @endif
                        </td>
                        <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 text-center bg-zinc-50 dark:bg-zinc-800/50 text-xs" rowspan="2">
                            @if (isset($row['derived1RM']) && $row['derived1RM'] !== null)
                                {{ number_format($row['derived1RM'], 1) }}
                            @else
                                <span class="text-zinc-400">-</span>
                            @endif
                        </td>
                        @foreach ($row['reps'] as $colIndex => $rep)
                            @php
                                $source = $row['sources'][$colIndex] ?? 'computed';
                                $sourceClass = $sourceColors[$source] ?? '';
                                $repsMeta = array_merge($cellMeta, ['field' => 'reps', 'setIndex' => $colIndex]);
                                $repsMetaJson = json_encode($repsMeta);
                            @endphp
                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center cursor-pointer select-none {{ $sourceClass }}"
                                :class="getBorderClasses({{ $repsRowIndex }}, {{ $colIndex }})"
                                @mousedown="handleCellMousedown({{ $repsRowIndex }}, {{ $colIndex }}, $event)"
                                @dblclick="handleCellDblClick({{ $repsRowIndex }}, {{ $colIndex }}, $event)"
                                @mouseenter="extendSelection({{ $repsRowIndex }}, {{ $colIndex }}, $event)"
                                x-init="registerCell({{ $repsRowIndex }}, {{ $colIndex }}, $el, {{ $repsMetaJson }})">
                                {{ $rep }}
                                @if ($source === 'manual')
                                    <span class="text-yellow-600 text-xs">*</span>
                                @elseif ($source === 'locked')
                                    <span class="text-purple-600 text-xs">🔒</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr class="bg-green-50 dark:bg-green-900/20">
                        @foreach ($row['weights'] as $colIndex => $weight)
                            @php
                                $source = $row['sources'][$colIndex] ?? 'computed';
                                $sourceClass = $source === 'manual' ? 'bg-yellow-100 dark:bg-yellow-900/30' : ($source === 'locked' ? 'bg-purple-100 dark:bg-purple-900/30' : '');
                                $weightsMeta = array_merge($cellMeta, ['field' => 'weight', 'setIndex' => $colIndex]);
                                $weightsMetaJson = json_encode($weightsMeta);
                            @endphp
                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center cursor-pointer select-none {{ $sourceClass }}"
                                :class="getBorderClasses({{ $weightsRowIndex }}, {{ $colIndex }})"
                                @mousedown="handleCellMousedown({{ $weightsRowIndex }}, {{ $colIndex }}, $event)"
                                @dblclick="handleCellDblClick({{ $weightsRowIndex }}, {{ $colIndex }}, $event)"
                                @mouseenter="extendSelection({{ $weightsRowIndex }}, {{ $colIndex }}, $event)"
                                x-init="registerCell({{ $weightsRowIndex }}, {{ $colIndex }}, $el, {{ $weightsMetaJson }})">
                                {{ number_format($weight, 1) }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</div>
