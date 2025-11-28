@props(['title', 'rows' => [], 'setCount' => 0, 'rowColor' => 'bg-blue-50', 'grid' => true, 'exerciseId' => null])

<div class="text-sm outline-none"
    @if ($grid) x-data="data_grid"
        tabindex="0"
        @click.outside="clearSelection()"
        @keydown.ctrl.c.prevent="copy()"
        @keydown.meta.c.prevent="copy()"
        @keydown.ctrl.v.prevent="paste()"
        @keydown.meta.v.prevent="paste()"
        @keydown.escape="cancelEdit()"
        @keydown.enter="commitEdit()" @endif>
    <flux:heading size="sm" class="mb-2">{{ $title }}</flux:heading>
    <table class="border-collapse border border-zinc-300 dark:border-zinc-600">
        <thead>
            <tr class="bg-zinc-100 dark:bg-zinc-800">
                <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2">W.S</th>
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
                        'weekUuid' => $row['weekUuid'] ?? null,
                        'sessionDay' => $row['sessionDay'] ?? null,
                        'sessionSlot' => $row['sessionSlot'] ?? null,
                    ];
                    $cellMetaJson = json_encode($cellMeta);
                @endphp
                <tr class="{{ $rowColor }}">
                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50" rowspan="2">
                        {{ $row['week'] }}.{{ $row['session'] }}</td>
                    @foreach ($row['reps'] as $colIndex => $rep)
                        @php
                            $repsMeta = array_merge($cellMeta, ['field' => 'reps', 'setIndex' => $colIndex]);
                            $repsMetaJson = json_encode($repsMeta);
                            $isRepsOverridden = ($row['repsOverridden'][$colIndex] ?? false);
                        @endphp
                        @if ($grid)
                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center cursor-pointer select-none {{ $isRepsOverridden ? 'bg-blue-100 dark:bg-blue-800/50' : '' }}"
                                :class="getBorderClasses({{ $repsRowIndex }}, {{ $colIndex }})"
                                @mousedown="handleCellMousedown({{ $repsRowIndex }}, {{ $colIndex }}, $event)"
                                @dblclick="handleCellDblClick({{ $repsRowIndex }}, {{ $colIndex }}, $event)"
                                @mouseenter="extendSelection({{ $repsRowIndex }}, {{ $colIndex }}, $event)"
                                x-init="registerCell({{ $repsRowIndex }}, {{ $colIndex }}, $el, {{ $repsMetaJson }})">{{ $rep }}</td>
                        @else
                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $isRepsOverridden ? 'bg-blue-100 dark:bg-blue-800/50' : '' }}">
                                {{ $rep }}</td>
                        @endif
                    @endforeach
                </tr>
                <tr class="bg-green-50 dark:bg-green-900/20">
                    @foreach ($row['weights'] as $colIndex => $weight)
                        @php
                            $weightsMeta = array_merge($cellMeta, ['field' => 'weight', 'setIndex' => $colIndex]);
                            $weightsMetaJson = json_encode($weightsMeta);
                            $isWeightsOverridden = ($row['weightsOverridden'][$colIndex] ?? false);
                        @endphp
                        @if ($grid)
                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center cursor-pointer select-none {{ $isWeightsOverridden ? 'bg-green-100 dark:bg-green-800/50' : '' }}"
                                :class="getBorderClasses({{ $weightsRowIndex }}, {{ $colIndex }})"
                                @mousedown="handleCellMousedown({{ $weightsRowIndex }}, {{ $colIndex }}, $event)"
                                @dblclick="handleCellDblClick({{ $weightsRowIndex }}, {{ $colIndex }}, $event)"
                                @mouseenter="extendSelection({{ $weightsRowIndex }}, {{ $colIndex }}, $event)"
                                x-init="registerCell({{ $weightsRowIndex }}, {{ $colIndex }}, $el, {{ $weightsMetaJson }})">{{ $weight }}</td>
                        @else
                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $isWeightsOverridden ? 'bg-green-100 dark:bg-green-800/50' : '' }}">
                                {{ $weight }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
