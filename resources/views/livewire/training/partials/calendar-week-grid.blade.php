@php
    $dayLabels = [];
    for ($i = 0; $i < 7; $i++) {
        $dayLabels[] = \Carbon\Carbon::now()->startOfWeek($this->weekStartsOn)->addDays($i)->format('D');
    }
@endphp

<div class="overflow-x-auto">
    <table class="border-separate border-spacing-0 text-sm border-t border-l border-zinc-300 dark:border-zinc-600 w-full table-fixed">
        <colgroup>
            <col style="width: 100px" />
            <col style="width: 30px" />
            @for ($i = 0; $i < 7; $i++)
                <col class="w-auto" />
            @endfor
        </colgroup>
        <thead>
            <tr class="bg-zinc-100 dark:bg-zinc-800">
                <th class="sticky left-0 z-10 bg-zinc-100 dark:bg-zinc-800 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 text-left text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                </th>
                <th class="bg-zinc-100 dark:bg-zinc-800 border-r border-b border-zinc-300 dark:border-zinc-600 px-1 py-2">
                </th>
                @foreach ($dayLabels as $dayLabel)
                    <th class="border-r border-b border-zinc-300 dark:border-zinc-600 px-2 py-2 text-center text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                        {{ $dayLabel }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($this->weekGridData as $week)
                <tr wire:key="week-grid-{{ $week['key'] }}-am">
                    <td rowspan="2" class="sticky left-0 z-10 bg-white dark:bg-zinc-900 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 text-xs font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap align-top">
                        <div>{{ $week['label'] }}</div>
                        <div class="text-[10px] text-zinc-400 dark:text-zinc-500">{{ $week['dateRange'] }}</div>
                    </td>
                    <td class="bg-white dark:bg-zinc-900 border-r border-b border-zinc-300 dark:border-zinc-600 px-1.5 py-1 text-[10px] text-zinc-400 dark:text-zinc-500">
                        AM
                    </td>
                    @foreach ($week['days'] as $day)
                        <td class="border-r border-b border-zinc-300 dark:border-zinc-600 px-1.5 py-1.5 align-top {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                            <div class="flex flex-col gap-0.5">
                                @foreach ($day['am'] as $prog)
                                    @if ($prog['color'])
                                        <button type="button" wire:click="editWeekSlot({{ $prog['trainingProgramId'] }}, '{{ $day['date'] }}', '{{ $prog['time'] }}')"
                                            class="text-[11px] px-1.5 py-0.5 rounded truncate text-left cursor-pointer hover:opacity-80 transition-opacity"
                                            style="{{ \Coda\Cms\Support\ColorPalette::solid($prog['color']) }}">
                                            {{ $prog['time'] }} – {{ $prog['name'] }}
                                        </button>
                                    @else
                                        <button type="button" wire:click="editWeekSlot({{ $prog['trainingProgramId'] }}, '{{ $day['date'] }}', '{{ $prog['time'] }}')"
                                            class="text-[11px] px-1.5 py-0.5 rounded truncate text-left cursor-pointer hover:opacity-80 transition-opacity bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                            {{ $prog['time'] }} – {{ $prog['name'] }}
                                        </button>
                                    @endif
                                @endforeach
                                <button type="button" wire:click="openWeekSlot('{{ $day['date'] }}', 'am')" class="flex items-center justify-center text-zinc-300 dark:text-zinc-600 hover:text-zinc-500 dark:hover:text-zinc-400 transition-colors py-0.5">
                                    <flux:icon.plus class="size-3.5" />
                                </button>
                            </div>
                        </td>
                    @endforeach
                </tr>
                <tr wire:key="week-grid-{{ $week['key'] }}-pm">
                    <td class="bg-white dark:bg-zinc-900 border-r border-b border-zinc-300 dark:border-zinc-600 px-1.5 py-1 text-[10px] text-zinc-400 dark:text-zinc-500">
                        PM
                    </td>
                    @foreach ($week['days'] as $day)
                        <td class="border-r border-b border-zinc-300 dark:border-zinc-600 px-1.5 py-1.5 align-top {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                            <div class="flex flex-col gap-0.5">
                                @foreach ($day['pm'] as $prog)
                                    @if ($prog['color'])
                                        <button type="button" wire:click="editWeekSlot({{ $prog['trainingProgramId'] }}, '{{ $day['date'] }}', '{{ $prog['time'] }}')"
                                            class="text-[11px] px-1.5 py-0.5 rounded truncate text-left cursor-pointer hover:opacity-80 transition-opacity"
                                            style="{{ \Coda\Cms\Support\ColorPalette::solid($prog['color']) }}">
                                            {{ $prog['time'] }} – {{ $prog['name'] }}
                                        </button>
                                    @else
                                        <button type="button" wire:click="editWeekSlot({{ $prog['trainingProgramId'] }}, '{{ $day['date'] }}', '{{ $prog['time'] }}')"
                                            class="text-[11px] px-1.5 py-0.5 rounded truncate text-left cursor-pointer hover:opacity-80 transition-opacity bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                            {{ $prog['time'] }} – {{ $prog['name'] }}
                                        </button>
                                    @endif
                                @endforeach
                                <button type="button" wire:click="openWeekSlot('{{ $day['date'] }}', 'pm')" class="flex items-center justify-center text-zinc-300 dark:text-zinc-600 hover:text-zinc-500 dark:hover:text-zinc-400 transition-colors py-0.5">
                                    <flux:icon.plus class="size-3.5" />
                                </button>
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
