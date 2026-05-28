@php
    $dayLabels = [];
    for ($i = 0; $i < 7; $i++) {
        $dayLabels[] = \Carbon\Carbon::now()->startOfWeek($this->weekStartsOn)->addDays($i)->format('D');
    }
    $allWeeks = $this->weekGridData;
    $pageSize = 2;
    $pages = array_chunk($allWeeks, $pageSize);
    $pageRanges = [];
    foreach ($pages as $pageIndex => $pageWeeks) {
        $pageRanges[$pageIndex] = [
            'start' => $pageWeeks[0]['days'][0]['date'],
            'end' => end($pageWeeks)['days'][6]['date'],
        ];
    }
    $groupId = property_exists($this, 'groupId') ? $this->groupId : ($this->group !== '' ? $this->group : '');
    $userId = property_exists($this, 'userId') ? ($this->userId ?? '') : ($this->user !== '' ? $this->user : '');
@endphp

<div
    x-data="{
    slotPages: {},
    loadingPages: {},
    async refresh(pageRanges) {
        await Promise.all(
            Object.entries(pageRanges).map(([pageIndex, range]) =>
                this.loadPage(pageIndex, range.start, range.end, true)
            )
        );
    },
    async loadPage(pageIndex, startDate, endDate, force = false) {
        if (!force && this.slotPages[pageIndex]) return;
        this.loadingPages[pageIndex] = true;
        const params = new URLSearchParams({ start: startDate, end: endDate });
        @if ($groupId && !$userId)
            params.set('group_id', '{{ $groupId }}');
        @elseif ($userId)
            params.set('user_id', '{{ $userId }}');
        @endif
        try {
            const resp = await fetch('{{ route('api.slot-week-page') }}?' + params);
            this.slotPages[pageIndex] = await resp.json();
        } finally {
            delete this.loadingPages[pageIndex];
        }
    },
    getSlots(pageIndex, date, period) {
        const page = this.slotPages[pageIndex];
        if (!page) return [];
        return page[date]?.[period] ?? [];
    },
    isLoaded(pageIndex) {
        return !!this.slotPages[pageIndex];
    },
    cardStyle(prog) {
        return prog.color ? 'background-color: var(--color-' + prog.color + '-500); color: white;' : '';
    }
}"
    x-on:schedule-grid-refresh.window="refresh(@js($pageRanges))"
    class="overflow-x-auto">
    <table class="border-separate border-spacing-0 text-sm border-t border-l border-zinc-300 dark:border-zinc-600 w-full table-fixed">
        <colgroup>
            <col style="width: 100px" />
            <col style="width: 30px" />
            @for ($i = 0; $i < 7; $i++)
                <col class="w-auto" />
            @endfor
            <col style="width: 44px" />
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
                <th class="border-r border-b border-zinc-300 dark:border-zinc-600 px-1 py-2 text-center text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pages as $pageIndex => $pageWeeks)
                @php
                    $pageStartDate = $pageWeeks[0]['days'][0]['date'];
                    $pageEndDate = end($pageWeeks)['days'][6]['date'];
                @endphp
                @foreach ($pageWeeks as $weekIdx => $week)
                    @php
                        $weekStartDate = $week['days'][0]['date'];
                        $copyFromWeeks = array_values(array_filter($allWeeks, fn (array $candidate): bool => $candidate['key'] !== $week['key']));
                        $copyForwardOptions = range(1, 8);
                    @endphp
                    <tr wire:key="week-grid-{{ $week['key'] }}-am"
                        @if ($weekIdx === 0) x-init="loadPage({{ $pageIndex }}, '{{ $pageStartDate }}', '{{ $pageEndDate }}')" @endif>
                        <td rowspan="2" class="sticky left-0 z-10 bg-white dark:bg-zinc-900 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 text-xs font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap align-top">
                            <div>{{ $week['label'] }}</div>
                            <div class="text-[10px] text-zinc-400 dark:text-zinc-500">{{ $week['dateRange'] }}</div>
                        </td>
                        <td class="bg-white dark:bg-zinc-900 border-r border-b border-zinc-300 dark:border-zinc-600 px-1.5 py-1 text-[10px] text-zinc-400 dark:text-zinc-500">
                            {{ __('AM') }}
                        </td>
                        @foreach ($week['days'] as $day)
                            <td
                                @if ($weekEditMode === 'edit')
                                    @click="$wire.quickCreateWeekSlot('{{ $day['date'] }}', 'am')"
                                @elseif ($weekEditMode === 'view')
                                    @click="$wire.openWeekSlot('{{ $day['date'] }}', 'am')"
                                @endif
                                class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 cursor-pointer {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                                <div class="flex flex-col gap-1 px-1.5 py-1.5">
                                    <template x-for="prog in getSlots({{ $pageIndex }}, '{{ $day['date'] }}', 'am')">
                                        <x-training.calendar-slot-card
                                            type="button"
                                            @click.stop="
                                                if ('{{ $weekEditMode }}' === 'remove') {
                                                    $wire.quickRemoveWeekSlot(prog.trainingProgramId, '{{ $day['date'] }}', prog.time)
                                                } else {
                                                    $wire.editWeekSlot(prog.trainingProgramId, '{{ $day['date'] }}', prog.time)
                                                }
                                            "
                                            class="w-full"
                                            color-expr="prog.color"
                                            time-expr="prog.time"
                                            name-expr="prog.name"
                                            subtitle-expr="(prog.userNames || []).join(', ')"
                                            status-color-expr="prog.statusColor"
                                        />
                                    </template>
                                    <div x-show="!isLoaded({{ $pageIndex }})" class="h-4 w-full animate-pulse rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                    <div class="flex items-center justify-center text-zinc-300 dark:text-zinc-600 py-1">
                                        <flux:icon.plus class="size-3.5" />
                                    </div>
                                </div>
                            </td>
                        @endforeach
                        <td rowspan="2" class="border-r border-b border-zinc-300 dark:border-zinc-600 px-1 py-1 align-middle text-center bg-zinc-50 dark:bg-zinc-800">
                            @if (($week['hasFutureDates'] ?? false) && (! empty($copyFromWeeks) || ! empty($copyForwardOptions)))
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-1" />
                                    <flux:menu>
                                        @if (! empty($copyFromWeeks))
                                            <flux:menu.submenu :heading="__('Copy From')">
                                                @foreach ($copyFromWeeks as $copyFromWeek)
                                                    <flux:menu.item wire:click="copyWeekSlots('{{ $copyFromWeek['days'][0]['date'] }}', '{{ $weekStartDate }}')">
                                                        {{ $copyFromWeek['label'] }} · {{ $copyFromWeek['dateRange'] }}
                                                    </flux:menu.item>
                                                @endforeach
                                            </flux:menu.submenu>
                                        @endif
                                        @if (! empty($copyForwardOptions))
                                            <flux:menu.submenu :heading="__('Copy To')">
                                                @foreach ($copyForwardOptions as $weekCount)
                                                    <flux:menu.item wire:click="requestCopyWeekSlotsForward('{{ $weekStartDate }}', {{ $weekCount }})">
                                                        {{ $weekCount === 1 ? __('Next Week') : __('Next :count Weeks', ['count' => $weekCount]) }}
                                                    </flux:menu.item>
                                                @endforeach
                                            </flux:menu.submenu>
                                        @endif
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="requestClearWeekSchedule('{{ $weekStartDate }}')">
                                            {{ __('Remove All') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </td>
                    </tr>
                    <tr wire:key="week-grid-{{ $week['key'] }}-pm">
                        <td class="bg-white dark:bg-zinc-900 border-r border-b border-zinc-300 dark:border-zinc-600 px-1.5 py-1 text-[10px] text-zinc-400 dark:text-zinc-500">
                            {{ __('PM') }}
                        </td>
                        @foreach ($week['days'] as $day)
                            <td
                                @if ($weekEditMode === 'edit')
                                    @click="$wire.quickCreateWeekSlot('{{ $day['date'] }}', 'pm')"
                                @elseif ($weekEditMode === 'view')
                                    @click="$wire.openWeekSlot('{{ $day['date'] }}', 'pm')"
                                @endif
                                class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 cursor-pointer {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                                <div class="flex flex-col gap-1 px-1.5 py-1.5">
                                    <template x-for="prog in getSlots({{ $pageIndex }}, '{{ $day['date'] }}', 'pm')">
                                        <x-training.calendar-slot-card
                                            type="button"
                                            @click.stop="
                                                if ('{{ $weekEditMode }}' === 'remove') {
                                                    $wire.quickRemoveWeekSlot(prog.trainingProgramId, '{{ $day['date'] }}', prog.time)
                                                } else {
                                                    $wire.editWeekSlot(prog.trainingProgramId, '{{ $day['date'] }}', prog.time)
                                                }
                                            "
                                            class="w-full"
                                            color-expr="prog.color"
                                            time-expr="prog.time"
                                            name-expr="prog.name"
                                            subtitle-expr="(prog.userNames || []).join(', ')"
                                            status-color-expr="prog.statusColor"
                                        />
                                    </template>
                                    <div x-show="!isLoaded({{ $pageIndex }})" class="h-4 w-full animate-pulse rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                    <div class="flex items-center justify-center text-zinc-300 dark:text-zinc-600 py-1">
                                        <flux:icon.plus class="size-3.5" />
                                    </div>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</div>
