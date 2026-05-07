@php
    $labelColumnWidth = 280;
    $dayCellMinWidth = 32;
    $dayCount = max(count($this->days), 1);
    $tableMinWidth = $labelColumnWidth + ($dayCount * $dayCellMinWidth);
    $labelCellStyle = "width: {$labelColumnWidth}px; min-width: {$labelColumnWidth}px; max-width: {$labelColumnWidth}px;";
    $dayCellStyle = "width: max({$dayCellMinWidth}px, calc((100% - {$labelColumnWidth}px) / {$dayCount})); min-width: {$dayCellMinWidth}px;";
@endphp
<div class="overflow-x-auto" x-data="{
    popover: { open: false, loading: false, slots: [], x: 0, y: 0, above: false },
    openMemberPopover(el, userId, date) {
        const rect = el.getBoundingClientRect();
        this.popover.x = rect.left + rect.width / 2;
        this.popover.y = rect.bottom + 4;
        this.popover.above = (this.popover.y + 200 > window.innerHeight);
        if (this.popover.above) this.popover.y = rect.top - 4;
        this.popover.slots = [];
        this.popover.loading = true;
        this.popover.open = true;
        fetch('{{ route('api.user-day-slots') }}?user_id=' + userId + '&date=' + date)
            .then(r => r.json())
            .then(d => { this.popover.slots = d; this.popover.loading = false; });
    },
    closePopover() { this.popover.open = false; this.popover.slots = []; },
}">
    <table class="w-full border-separate border-spacing-0 text-sm border-t border-l border-zinc-300 dark:border-zinc-600" style="min-width: {{ $tableMinWidth }}px;">
        <colgroup>
            <col style="{{ $labelCellStyle }}">
            @foreach ($this->days as $day)
                <col style="{{ $dayCellStyle }}">
            @endforeach
        </colgroup>
        <thead>
            <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                <th rowspan="3"
                    class="sticky left-0 z-10 bg-zinc-100 dark:bg-zinc-800 border-r border-b border-zinc-300 dark:border-zinc-600 px-2 py-2 text-left min-w-fit"
                    style="{{ $labelCellStyle }}">
                </th>
                @foreach ($this->months as $month)
                    <th colspan="{{ $month['colspan'] }}"
                        class="border-r border-b border-zinc-300 dark:border-zinc-600 px-1.5 py-1 text-center text-xs font-semibold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800">
                        {{ $month['label'] }}
                    </th>
                @endforeach
            </tr>
            <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                @foreach ($this->weeks as $week)
                    <th colspan="{{ $week['colspan'] }}"
                        class="border-r border-b border-zinc-300 dark:border-zinc-600 px-0.5 py-0.5 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400 {{ $week['week'] % 2 !== 0 ? 'bg-zinc-100/50 dark:bg-zinc-700/20' : '' }}">
                        W{{ $week['week'] }}
                    </th>
                @endforeach
            </tr>
            <tr class="bg-zinc-100 dark:bg-zinc-800">
                @foreach ($this->days as $day)
                    <th
                        class="border-r border-b border-zinc-300 dark:border-zinc-600 px-1 py-2 text-center {{ $day['isToday'] ? 'bg-blue-100 dark:bg-blue-900/30' : ($day['oddWeek'] ? 'bg-zinc-100/50 dark:bg-zinc-700/20' : '') }}"
                        style="{{ $dayCellStyle }}">
                        <div class="text-[10px] leading-tight">{{ $day['label'] }}</div>
                        <div class="font-medium text-[10px]">{{ $day['day'] }}</div>
                    </th>
                @endforeach
            </tr>
        </thead>
        @foreach ($this->overviewData as $groupRow)
            @php
                $members = collect($groupRow['members'])->map(fn ($m) => ['id' => $m['user']->id, 'name' => $m['user']->name])->values()->all();
            @endphp
            <tbody x-data="{
                expanded: false,
                memberColors: null,
                loading: false,
                members: {{ \Illuminate\Support\Js::from($members) }},
                days: {{ \Illuminate\Support\Js::from(collect($this->days)->map(fn ($d) => ['date' => $d['date'], 'isToday' => $d['isToday'], 'oddWeek' => $d['oddWeek']])->values()->all()) }},
                dates: {{ \Illuminate\Support\Js::from($groupRow['dates']) }},
                dateColors: {{ \Illuminate\Support\Js::from($groupRow['dateColors']) }},
                memberRows: [],
                async toggle() {
                    this.expanded = !this.expanded;
                    if (this.expanded && !this.memberColors && !this.loading) {
                        this.loading = true;
                        const resp = await fetch('{{ route('api.slot-member-colors') }}?group_id={{ $groupRow['group']->id }}&start={{ $this->days[0]['date'] }}&end={{ $this->days[count($this->days) - 1]['date'] }}');
                        this.memberColors = await resp.json();
                        this.loading = false;
                        this.buildMemberRows();
                    }
                },
                buildMemberRows() {
                    if (!this.memberColors) return;
                    this.memberRows = this.members.map(m => {
                        const dayCells = this.days.map(d => {
                            const colors = this.memberColors[m.id]?.[d.date];
                            if (!colors) return { style: '', hasData: false };
                            const entries = Object.entries(colors);
                            const total = entries.reduce((s, [, c]) => s + c, 0);
                            if (entries.length === 1) {
                                const c = entries[0][0];
                                return { style: 'background-color: ' + (c === '_none' ? 'var(--color-zinc-300)' : 'var(--color-' + c + '-600)') + ';', hasData: true };
                            }
                            let pos = 0;
                            const stops = entries.map(([c, cnt]) => {
                                const pct = (cnt / total * 100).toFixed(2);
                                const css = c === '_none' ? 'var(--color-zinc-300)' : 'var(--color-' + c + '-600)';
                                const s = css + ' ' + pos + '% ' + (parseFloat(pos) + parseFloat(pct)) + '%';
                                pos = parseFloat(pos) + parseFloat(pct);
                                return s;
                            });
                            return { style: 'background: linear-gradient(to bottom, ' + stops.join(', ') + ');', hasData: true };
                        });
                        return { id: m.id, name: m.name, cells: dayCells };
                    });
                },
                buildGradient(colorCounts) {
                    if (!colorCounts) return '';
                    let entries = Object.entries(colorCounts);
                    let total = entries.reduce((s, [, c]) => s + c, 0);
                    if (entries.length === 1) {
                        let c = entries[0][0];
                        return 'background-color: ' + (c === '_none' ? 'var(--color-zinc-300)' : 'var(--color-' + c + '-600)') + ';';
                    }
                    let pos = 0;
                    let stops = entries.map(([c, cnt]) => {
                        let pct = (cnt / total * 100).toFixed(2);
                        let css = c === '_none' ? 'var(--color-zinc-300)' : 'var(--color-' + c + '-600)';
                        let s = css + ' ' + pos + '% ' + (parseFloat(pos) + parseFloat(pct)) + '%';
                        pos = parseFloat(pos) + parseFloat(pct);
                        return s;
                    });
                    return 'background: linear-gradient(to bottom, ' + stops.join(', ') + ');';
                },
            }" wire:key="overview-group-{{ $groupRow['group']->id }}-{{ $this->days[0]['date'] }}-{{ $this->days[count($this->days) - 1]['date'] }}">
                <tr>
                    <td
                        class="sticky left-0 z-10 border-r border-b border-zinc-300 dark:border-zinc-600 px-2 py-2 font-semibold text-zinc-700 dark:text-zinc-300 whitespace-nowrap min-w-fit bg-zinc-100 dark:bg-zinc-800">
                        <div class="flex items-center gap-2">
                            <button @click.stop="toggle()" class="shrink-0 text-zinc-400 transition-colors">
                                <flux:icon.chevron-right class="size-4 transition-transform duration-200"
                                    ::class="expanded && 'rotate-90'" />
                            </button>
                            <button type="button" wire:click="selectFromOverview({{ $groupRow['group']->id }})"
                                class="hover:underline">
                                {{ $groupRow['group']->name }}
                            </button>
                            <span
                                class="text-xs font-normal text-zinc-500 dark:text-zinc-400">({{ count($groupRow['members']) }})</span>
                        </div>
                    </td>
                    <template x-for="(day, dayIdx) in days" :key="'g-{{ $groupRow['group']->id }}-' + day.date">
                        <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0"
                            :class="!dates[day.date] && (day.isToday ? 'bg-blue-50/50 dark:bg-blue-900/10' : (day.oddWeek ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : ''))"
                            :style="dates[day.date] ? buildGradient(dateColors[day.date]) : ''">
                            <div class="aspect-square"></div>
                        </td>
                    </template>
                </tr>

                <template x-if="expanded && memberRows.length > 0">
                    <template x-for="member in memberRows" :key="member.id">
                        <tr>
                            <td class="sticky left-0 z-10 border-r border-b border-zinc-300 dark:border-zinc-600 px-2 py-2 pl-7 font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap min-w-fit bg-white dark:bg-zinc-900">
                                <button type="button"
                                    @click="$wire.selectFromOverview({{ $groupRow['group']->id }}, member.id)"
                                    class="hover:underline" x-text="member.name">
                                </button>
                            </td>
                            <template x-for="(cell, idx) in member.cells" :key="idx">
                                <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0"
                                    :style="cell.style"
                                    :class="cell.hasData && 'cursor-pointer hover:brightness-90'"
                                    @click="cell.hasData && openMemberPopover($el, member.id, days[idx].date)">
                                    <div class="aspect-square"></div>
                                </td>
                            </template>
                        </tr>
                    </template>
                </template>

                <template x-if="expanded && loading">
                    <tr>
                        <td colspan="{{ count($this->days) + 1 }}" class="border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-3 text-center text-xs text-zinc-400">
                            <div class="flex items-center justify-center gap-2">
                                <div class="h-4 w-4 animate-spin rounded-full border-2 border-zinc-300 border-t-zinc-600"></div>
                                {{ __('Loading...') }}
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        @endforeach
    </table>
    <template x-teleport="body">
        <div x-show="popover.open"
             x-cloak
             @click.outside="closePopover()"
             class="fixed z-50 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 p-2 min-w-[10rem] max-w-[16rem]"
             :style="'left: ' + popover.x + 'px; top: ' + popover.y + 'px; transform: translateX(-50%)' + (popover.above ? ' translateY(-100%)' : '')">
            <div class="flex flex-col gap-1">
                <template x-if="popover.loading">
                    <div class="flex items-center justify-center py-2">
                        <div class="h-4 w-4 animate-spin rounded-full border-2 border-zinc-300 border-t-zinc-600"></div>
                    </div>
                </template>
                <template x-if="!popover.loading && popover.slots.length > 0">
                    <template x-for="(slot, si) in popover.slots" :key="si">
                        <x-training.calendar-slot-card
                            as="div"
                            compact
                            color-expr="slot.color"
                            time-expr="slot.time"
                            name-expr="slot.name"
                            status-color-expr="slot.statusColor"
                        />
                    </template>
                </template>
            </div>
        </div>
    </template>
</div>
