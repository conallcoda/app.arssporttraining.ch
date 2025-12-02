@props(['title', 'rows' => [], 'setCount' => 4, 'exerciseId' => null, 'progression' => null])

@php
    $sourceColors = [
        'computed' => '',
        'manual' => 'bg-yellow-50 dark:bg-yellow-900/20',
        'locked' => 'bg-purple-50 dark:bg-purple-900/20',
    ];
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
    <div class="flex items-center justify-between mb-2">
        <flux:heading size="sm">{{ $title }}</flux:heading>
        @if ($progression)
            <div class="text-xs text-zinc-500">
                1RM: {{ number_format($progression->derived1RM, 1) }}kg → {{ number_format($progression->target1RM, 1) }}kg
            </div>
        @endif
    </div>
    <table class="border-collapse border border-zinc-300 dark:border-zinc-600">
        <thead>
            <tr class="bg-zinc-100 dark:bg-zinc-800">
                <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2">W.S</th>
                <th class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 text-xs text-zinc-500">Anchor</th>
                @for ($i = 0; $i < $setCount; $i++)
                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2">Set {{ $i + 1 }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
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
                    <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-xs text-center text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50" rowspan="2">
                        @if (isset($row['anchorReps']) && isset($row['anchorWeight']))
                            <div>{{ $row['anchorReps'] }}r</div>
                            <div>{{ number_format($row['anchorWeight'], 1) }}</div>
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
        </tbody>
    </table>
</div>
