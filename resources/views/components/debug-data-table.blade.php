@props(['title', 'rows' => [], 'setCount' => 0, 'rowColor' => 'bg-blue-50', 'grid' => true])

<div class="text-sm outline-none"
    @if ($grid) x-data="data_grid"
        tabindex="0"
        @click.outside="clearSelection()"
        @mousedown="$el.focus()"
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
                @endphp
                <tr class="{{ $rowColor }}">
                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold" rowspan="2">
                        {{ $row['week'] }}.{{ $row['session'] }}</td>
                    @foreach ($row['reps'] as $colIndex => $rep)
                        @if ($grid)
                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center cursor-pointer select-none"
                                :class="getBorderClasses({{ $repsRowIndex }}, {{ $colIndex }})"
                                @mousedown.prevent="startSelection({{ $repsRowIndex }}, {{ $colIndex }}, $event)"
                                @mouseenter="extendSelection({{ $repsRowIndex }}, {{ $colIndex }}, $event)"
                                @dblclick.prevent="startEdit({{ $repsRowIndex }}, {{ $colIndex }})"
                                x-init="registerCell({{ $repsRowIndex }}, {{ $colIndex }}, $el)">{{ $rep }}</td>
                        @else
                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center">
                                {{ $rep }}</td>
                        @endif
                    @endforeach
                </tr>
                <tr>
                    @foreach ($row['weights'] as $colIndex => $weight)
                        @if ($grid)
                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center cursor-pointer select-none"
                                :class="getBorderClasses({{ $weightsRowIndex }}, {{ $colIndex }})"
                                @mousedown.prevent="startSelection({{ $weightsRowIndex }}, {{ $colIndex }}, $event)"
                                @mouseenter="extendSelection({{ $weightsRowIndex }}, {{ $colIndex }}, $event)"
                                @dblclick.prevent="startEdit({{ $weightsRowIndex }}, {{ $colIndex }})"
                                x-init="registerCell({{ $weightsRowIndex }}, {{ $colIndex }}, $el)">{{ $weight }}</td>
                        @else
                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center">
                                {{ $weight }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
