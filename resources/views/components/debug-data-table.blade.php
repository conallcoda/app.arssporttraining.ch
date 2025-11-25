@props([
    'title',
    'rows' => [],
    'setCount' => 0,
    'rowColor' => 'bg-blue-50',
])

<div class="text-xs" x-data="data_grid" tabindex="0" @click.outside="clearSelection()">
    <div class="font-bold mb-2">{{ $title }}</div>
    <table class="border-collapse border border-zinc-300">
        <thead>
            <tr class="bg-zinc-100">
                <th class="border border-zinc-300 px-2 py-1">W.S</th>
                @for ($i = 0; $i < $setCount; $i++)
                    <th class="border border-zinc-300 px-2 py-1">Set {{ $i + 1 }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $rowIndex => $row)
                @php
                    $repsRowIndex = $rowIndex * 2;
                    $weightsRowIndex = $rowIndex * 2 + 1;
                @endphp
                <tr class="{{ $rowColor }}">
                    <td class="border border-zinc-300 px-2 py-1 font-bold" rowspan="2">{{ $row['week'] }}.{{ $row['session'] }}</td>
                    @foreach ($row['reps'] as $colIndex => $rep)
                        <td
                            class="border border-zinc-300 px-2 py-1 text-center cursor-pointer select-none"
                            :class="getBorderClasses({{ $repsRowIndex }}, {{ $colIndex }})"
                            @mousedown.prevent="startSelection({{ $repsRowIndex }}, {{ $colIndex }})"
                            @mouseenter="extendSelection({{ $repsRowIndex }}, {{ $colIndex }})"
                            x-init="registerCell({{ $repsRowIndex }}, {{ $colIndex }}, $el)"
                        >{{ $rep }}</td>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($row['weights'] as $colIndex => $weight)
                        <td
                            class="border border-zinc-300 px-2 py-1 text-center cursor-pointer select-none"
                            :class="getBorderClasses({{ $weightsRowIndex }}, {{ $colIndex }})"
                            @mousedown.prevent="startSelection({{ $weightsRowIndex }}, {{ $colIndex }})"
                            @mouseenter="extendSelection({{ $weightsRowIndex }}, {{ $colIndex }})"
                            x-init="registerCell({{ $weightsRowIndex }}, {{ $colIndex }}, $el)"
                        >{{ $weight }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
