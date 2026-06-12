@php
    [$gridStart, $gridEnd] = $this->dateRange();
    $labelColumnMinWidth = 120;
    $dayCellMinWidth = 32;
    $dayCount = max(count($this->days), 1);
    $tableMinWidth = $labelColumnMinWidth + ($dayCount * $dayCellMinWidth);
    $labelCellStyle = "width: 1%; min-width: {$labelColumnMinWidth}px;";
    $dayCellStyle = "width: {$dayCellMinWidth}px; min-width: {$dayCellMinWidth}px; max-width: {$dayCellMinWidth}px;";
    $statusBarColors = \App\Models\Training\TrainingProgramSlotStatusEnum::barColorMap();
@endphp
<div>
<div class="overflow-x-auto" wire:key="grid-{{ $this->groupId }}-{{ $this->userId ?? 'group' }}-{{ md5(json_encode($this->athleteSlotOrder)) }}" x-data="calendar_slot_popover({ groupId: '{{ $this->groupId }}', userId: '{{ $this->userId ?? '' }}', startDate: '{{ $gridStart->format('Y-m-d') }}', endDate: '{{ $gridEnd->format('Y-m-d') }}', gridCellsUrl: '{{ route('api.program-grid-cells') }}', slotDetailsUrl: '{{ route('api.slot-details') }}', days: {{ \Illuminate\Support\Js::from($this->days) }}, athleteSlotOrder: {{ \Illuminate\Support\Js::from($this->athleteSlotOrder) }}, statusBarColors: {{ \Illuminate\Support\Js::from($statusBarColors) }}, wireId: '{{ $this->getId() }}' })">
    <table class="border-separate border-spacing-0 text-sm border-t border-l border-zinc-300 dark:border-zinc-600" style="width: max-content; min-width: {{ $tableMinWidth }}px;">
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
        @if ($this->programs->isNotEmpty())
            <tbody x-data="calendar_cell_select({ type: 'notes' })" data-cell-select-id="notes-notes" wire:ignore.self @keydown.escape.window="clearSelection()">
                <tr>
                    <td class="sticky left-0 z-20 bg-zinc-100 dark:bg-zinc-800 border-r border-b border-zinc-300 dark:border-zinc-600 px-2 py-2 min-w-fit whitespace-nowrap text-xs font-medium text-zinc-500 dark:text-zinc-400">
                        {{ __('Notes') }}
                    </td>
                    <td colspan="{{ count($this->days) }}"
                        class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 relative"
                        style="height: {{ max(1, $this->allBlocks['laneCount']) * 28 + 8 }}px">
                        <div class="absolute inset-0 flex">
                            <template x-for="(day, dayIdx) in days" :key="'note-' + day.date">
                                <div @mousedown.stop="startDrag(dayIdx, day.date, $event)"
                                     @mouseover="dragOver(dayIdx, day.date)"
                                     @contextmenu="showContextMenu($event, dayIdx, day.date)"
                                     class="flex-1 cursor-pointer h-full border-r border-zinc-300 dark:border-zinc-600 last:border-r-0 select-none"
                                     :class="[
                                         day.oddWeek ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '',
                                         (endIdx !== null ? (dayIdx >= Math.min(anchorIdx, endIdx) && dayIdx <= Math.max(anchorIdx, endIdx)) : anchorIdx === dayIdx) && 'ring ring-inset ring-black dark:ring-white'
                                     ]">
                                </div>
                            </template>
                        </div>
                        @foreach ($this->allBlocks['notes'] as $block)
                            <div wire:click.stop="editBlock({{ $block['id'] }})"
                                 wire:key="block-{{ $block['id'] }}"
                                 class="absolute cursor-pointer z-10 px-0.5"
                                 style="left: {{ ($block['startIdx'] / $this->allBlocks['totalDays']) * 100 }}%; width: {{ ($block['colspan'] / $this->allBlocks['totalDays']) * 100 }}%; top: {{ $block['lane'] * 28 + 4 }}px; height: 24px;">
                                <div class="rounded-sm flex items-center justify-center h-full px-1"
                                     style="{{ \Coda\Cms\Support\ColorPalette::solid($block['color']) }}">
                                    <span class="text-xs font-medium text-white truncate">{{ $block['note'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </td>
                </tr>
                <template x-teleport="body">
                    <div x-show="contextMenu"
                         x-cloak
                         @click.outside="clearSelection()"
                         class="fixed z-50 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 min-w-[160px]"
                         :style="'left: ' + contextMenuX + 'px; top: ' + contextMenuY + 'px'">
                        <button type="button"
                                @click="performAction()"
                                class="w-full text-left px-3 py-1.5 text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700">
                            {{ __('Add Note') }}
                        </button>
                    </div>
                </template>
            </tbody>
        @endif
        @if ($this->visibleMetrics)
            <tbody x-data="{ ...metric_cell_popover(), expanded: $persist(false).as('cal-metrics'), metricSummaryDates: {{ \Illuminate\Support\Js::from($this->metricSummaryDates) }} }"
                   x-init="if (expanded && !$wire.metricsLoaded) $wire.loadMetrics()"
                   wire:key="metrics-section-{{ $metricsRenderKey }}">
                <tr>
                    <td @click="expanded = !expanded; if (expanded && !$wire.metricsLoaded) $wire.loadMetrics()"
                        class="sticky left-0 z-10 cursor-pointer border-r border-b border-zinc-300 dark:border-zinc-600 px-2 py-2 font-semibold text-zinc-700 dark:text-zinc-300 whitespace-nowrap min-w-fit bg-zinc-300 dark:bg-zinc-700">
                        <div class="flex items-center gap-2">
                            <flux:icon.chevron-right class="size-4 transition-transform duration-200"
                                ::class="expanded && 'rotate-90'" />
                            <span>{{ __('Metrics') }}</span>
                        </div>
                    </td>
                    <template x-for="day in days" :key="'ms-' + day.date">
                        <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0.5"
                            style="{{ $dayCellStyle }}"
                            :class="day.oddWeek ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : ''">
                            <div class="aspect-square rounded-sm"
                                :class="metricSummaryDates[day.date] !== undefined && 'bg-zinc-300 dark:bg-zinc-700'"></div>
                        </td>
                    </template>
                </tr>

                @if ($metricsLoaded)
                    @foreach ($this->visibleMetrics as $metricCase)
                        @php $metricRowData = $this->getMetricRowData($metricCase->value); @endphp
                        <tr wire:key="metric-row-{{ $metricCase->value }}-{{ $metricsRenderKey }}" x-show="expanded" x-cloak x-data="{ metricData: {{ \Illuminate\Support\Js::from($metricRowData) }} }">
                            <td class="sticky left-0 z-10 border-r border-b border-zinc-300 dark:border-zinc-600 pl-5 pr-2 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 whitespace-nowrap min-w-fit bg-white dark:bg-zinc-900">
                                {{ $metricCase->label() }}
                                @if (isset($this->currentMetricValues[$metricCase->value]))
                                    @php $currentMetric = $this->currentMetricValues[$metricCase->value]; @endphp
                                    <div class="mt-1">
                                        @if ($currentMetric['isAvailable'])
                                            <flux:badge
                                                size="sm"
                                                color="zinc"
                                                class="text-xs cursor-pointer"
                                                title="{{ __('Current value. Recorded') }} {{ $currentMetric['recorded_at'] }}"
                                                wire:click="openCurrentMetric('{{ $metricCase->value }}')"
                                            >
                                                {{ $currentMetric['summary'] }}
                                            </flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc" class="text-xs">
                                                {{ $currentMetric['summary'] }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                @elseif ($this->userId === null && isset($this->groupCurrentMetricValues[$metricCase->value]))
                                    @php $groupMetric = $this->groupCurrentMetricValues[$metricCase->value]; @endphp
                                    <div class="mt-1" x-data="{ showPopover: false, popX: 0, popY: 0 }">
                                        <flux:badge size="sm" color="zinc" class="text-xs cursor-pointer" @click.stop="let r = $el.getBoundingClientRect(); popX = r.left; popY = r.bottom + 4; showPopover = !showPopover">
                                            {{ $groupMetric['withValue'] }}/{{ $groupMetric['total'] }}
                                        </flux:badge>
                                        <template x-teleport="body">
                                            <div x-show="showPopover" x-cloak
                                                 @click.outside="showPopover = false"
                                                 class="fixed z-50 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 p-2 min-w-[10rem] max-w-[16rem]"
                                                 :style="'left: ' + popX + 'px; top: ' + popY + 'px'">
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach ($groupMetric['members'] as $member)
                                                        <flux:badge
                                                            color="{{ $member['label'] ? 'zinc' : 'red' }}"
                                                            class="px-2 py-1 cursor-pointer text-xs"
                                                            wire:click="openGroupCurrentMetricEdit({{ $member['user_id'] }}, '{{ $metricCase->value }}')"
                                                        >
                                                            {{ $member['name'] }}: {{ $member['label'] ?? '—' }}
                                                        </flux:badge>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                @endif
                            </td>
                            <template x-for="day in days" :key="'m-{{ $metricCase->value }}-' + day.date">
                                <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0.5 cursor-pointer hover:brightness-95 dark:hover:brightness-125"
                                    style="{{ $dayCellStyle }}"
                                    :class="day.oddWeek ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : ''"
                                    @click="metricData[day.date]
                                        ? ({{ $this->userId !== null ? 'true' : 'false' }}
                                            ? $wire.openMetricCell('{{ $metricCase->value }}', day.date)
                                            : openPopover($el, '{{ $metricCase->value }}', day.date, metricData[day.date]))
                                        : ({{ $this->userId !== null ? 'true' : 'false' }}
                                            ? $wire.openMetricCell('{{ $metricCase->value }}', day.date)
                                            : $wire.openGroupMetricCell('{{ $metricCase->value }}', day.date))">
                                    <div x-show="metricData[day.date]"
                                        class="w-full aspect-square flex items-center justify-center text-[10px] font-medium text-white rounded-sm"
                                        :class="metricData[day.date]?.colorClass ?? 'bg-zinc-500/80 dark:bg-zinc-500/60'"
                                        x-text="metricData[day.date]?.label ?? metricData[day.date]?.count ?? ''">
                                    </div>
                                    <div x-show="!metricData[day.date]"
                                        class="aspect-square flex items-center justify-center group/empty">
                                        <svg class="size-3 text-zinc-400 dark:text-zinc-500 opacity-0 group-hover/empty:opacity-100 transition-opacity" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    </div>
                                </td>
                            </template>
                        </tr>
                    @endforeach
                @endif
                <template x-teleport="body">
                    <div x-show="open"
                         x-cloak
                         id="metric-cell-popover"
                         class="fixed z-50 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 p-2 min-w-[10rem] max-w-[16rem]"
                         :style="'left: ' + x + 'px; top: ' + y + 'px; transform: translateX(-50%)' + (_above ? ' translateY(-100%)' : '')">
                        <div class="flex flex-col gap-1.5">
                            <template x-for="entry in entries" :key="entry.submission_id">
                                <button type="button"
                                    @click="editEntry(entry.user_id, entry.submission_id)"
                                    class="flex flex-col px-2 py-1.5 rounded-lg text-left cursor-pointer hover:opacity-80 transition-opacity"
                                    :class="entry.colorClass ?? 'bg-zinc-500/80 dark:bg-zinc-500/60'">
                                    <span class="text-[10px] text-white opacity-80" x-text="entry.summary"></span>
                                    <span class="text-[10px] text-white opacity-80 truncate" x-text="entry.athlete"></span>
                                </button>
                            </template>
                            <button x-show="canAdd"
                                type="button"
                                @click="addNew()"
                                class="flex items-center justify-center w-full py-1 rounded-lg cursor-pointer text-zinc-400 dark:text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-all">
                                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            </button>
                        </div>
                    </div>
                </template>
            </tbody>
        @endif
        @foreach ($this->groupedPrograms as $categoryId => $group)
            @php
                $category = $group['category'];
                $groupEntries = $group['entries'];
                $categoryColorClass = $category?->color
                    ? \Coda\Cms\Support\ColorPalette::solidClasses($category->color)
                    : 'bg-zinc-100 dark:bg-zinc-800';
            @endphp
            @php
                $catBlocks = $this->categoryBlocks[$categoryId] ?? ['notes' => [], 'laneCount' => 0, 'totalDays' => count($this->days)];
                $blockDayMap = [];
                foreach ($catBlocks['notes'] as $catBlock) {
                    for ($di = $catBlock['startIdx']; $di <= $catBlock['endIdx']; $di++) {
                        $blockDayMap[$di] = ['id' => $catBlock['id'], 'note' => $catBlock['note']];
                    }
                }
                $categoryBlockBgClass = $category?->color
                    ? \Coda\Cms\Support\ColorPalette::blockTint($category->color)
                    : '';
            @endphp
            @php
                $hasBlocks = !empty($catBlocks['notes']);
                $isFirst = $loop->first;
                $labelRowspan = 1 + ($hasBlocks ? 1 : 0);
                $blockStartMap = [];
                if ($hasBlocks) {
                    foreach ($catBlocks['notes'] as $catBlock) {
                        $blockStartMap[$catBlock['startIdx']] = $catBlock;
                    }
                }
            @endphp
            <tbody x-data="{ ...calendar_cell_select({ type: 'category', categoryId: {{ $categoryId }}, persistKey: 'cal-cat-{{ $categoryId }}' }), blockDayMap: {{ \Illuminate\Support\Js::from($blockDayMap) }} }"
                  data-cell-select-id="category-{{ $categoryId }}"
                  @keydown.escape.window="clearSelection()"
                  wire:ignore.self
                  wire:key="category-{{ $categoryId }}-{{ md5(json_encode($catBlocks)) }}">
                {{-- Block labels row (only if blocks exist) --}}
                @if ($hasBlocks)
                    <tr>
                        <td @click="expanded = !expanded"
                            rowspan="{{ $labelRowspan }}"
                            class="sticky left-0 z-10 cursor-pointer border-r border-b border-zinc-300 dark:border-zinc-600 px-2 py-2 font-semibold text-zinc-700 dark:text-zinc-300 whitespace-nowrap min-w-fit {{ $categoryColorClass }}">
                            <div class="flex items-center gap-2">
                                <flux:icon.chevron-right class="size-4 transition-transform duration-200"
                                    ::class="expanded && 'rotate-90'" />
                                <span>{{ $category ? ($category->short_name ?: strtoupper(substr($category->name, 0, 3))) : 'Uncategorized' }}</span>
                                <span class="text-xs font-normal text-zinc-500 dark:text-zinc-200">({{ $groupEntries->count() }})</span>
                            </div>
                        </td>
                        @php $skipUntil = -1; @endphp
                        @foreach ($this->days as $dayIdx => $day)
                            @if ($dayIdx <= $skipUntil)
                                @continue
                            @endif
                            @if (isset($blockStartMap[$dayIdx]))
                                @php
                                    $block = $blockStartMap[$dayIdx];
                                    $span = $block['colspan'];
                                    $skipUntil = $block['endIdx'];
                                @endphp
                                    <td wire:click="editBlock({{ $block['id'] }})"
                                        colspan="{{ $span }}"
                                        class="border-r border-b border-zinc-300 dark:border-zinc-600 px-1 cursor-pointer max-w-0 overflow-hidden {{ $categoryBlockBgClass }}"
                                        style="height: 26px">
                                        <span class="text-sm font-medium truncate block text-center text-zinc-600 dark:text-zinc-300">{{ $block['note'] }}</span>
                                    </td>
                            @else
                                <td @mousedown.stop="startDrag({{ $dayIdx }}, '{{ $day['date'] }}', $event)"
                                    @mouseover="dragOver({{ $dayIdx }}, '{{ $day['date'] }}')"
                                    @contextmenu="showContextMenu($event, {{ $dayIdx }}, '{{ $day['date'] }}')"
                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 cursor-pointer select-none"
                                    :class="(endIdx !== null ? ({{ $dayIdx }} >= Math.min(anchorIdx, endIdx) && {{ $dayIdx }} <= Math.max(anchorIdx, endIdx)) : anchorIdx === {{ $dayIdx }}) && 'ring ring-inset ring-black dark:ring-white'"
                                    style="{{ $dayCellStyle }} height: 22px;"></td>
                            @endif
                        @endforeach
                    </tr>
                @endif

                @php $categoryProgramIds = $groupEntries->pluck('id')->all(); @endphp
                {{-- Indicator squares row --}}
                <tr>
                    @if (!$hasBlocks)
                        <td @click="expanded = !expanded"
                            class="sticky left-0 z-10 cursor-pointer border-r border-b border-zinc-300 dark:border-zinc-600 px-2 py-2 font-semibold text-zinc-700 dark:text-zinc-300 whitespace-nowrap min-w-fit {{ $categoryColorClass }}">
                            <div class="flex items-center gap-2">
                                <flux:icon.chevron-right class="size-4 transition-transform duration-200"
                                    ::class="expanded && 'rotate-90'" />
                                <span>{{ $category ? ($category->short_name ?: strtoupper(substr($category->name, 0, 3))) : 'Uncategorized' }}</span>
                                <span class="text-xs font-normal text-zinc-500 dark:text-zinc-200">({{ $groupEntries->count() }})</span>
                            </div>
                        </td>
                    @endif
                    <template x-for="(day, dayIdx) in days" :key="'ind-{{ $categoryId }}-' + day.date">
                        <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0.5 cursor-pointer hover:brightness-95 dark:hover:brightness-125"
                            style="{{ $dayCellStyle }}"
                            :class="[
                                blockDayMap[dayIdx] ? '{{ $categoryBlockBgClass }}' : (day.oddWeek ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : ''),
                                (endIdx !== null ? (dayIdx >= Math.min(anchorIdx, endIdx) && dayIdx <= Math.max(anchorIdx, endIdx)) : anchorIdx === dayIdx) && 'ring ring-inset ring-black dark:ring-white'
                            ]"
                            @mousedown.stop="!blockDayMap[dayIdx] && startDrag(dayIdx, day.date, $event)"
                            @mouseover="!blockDayMap[dayIdx] && dragOver(dayIdx, day.date)"
                            @contextmenu="!blockDayMap[dayIdx] && showContextMenu($event, dayIdx, day.date)"
                            @click="blockDayMap[dayIdx] && $wire.editBlock(blockDayMap[dayIdx].id)">
                            <div class="aspect-square rounded-sm"
                                :class="hasCategoryData({{ json_encode($categoryProgramIds) }}, day.date) && '{{ $categoryColorClass }}'"></div>
                        </td>
                    </template>
                </tr>

                @foreach ($groupEntries as $entry)
                    @php
                        $categoryColor = $entry->program->exerciseCategory?->color;
                        $colorClass = $categoryColor
                            ? \Coda\Cms\Support\ColorPalette::solidClasses($categoryColor)
                            : '';
                        $exercises = $entry->program->exercises
                            ->filter(fn ($exercise) => ($exercise->pivot->type ?? 'main') === 'main')
                            ->sortBy('pivot.sort');
                    @endphp
                    <tr wire:key="program-{{ $entry->id }}" x-show="expanded" x-cloak class="group/program">
                        <td
                            class="sticky left-0 z-10 border-r border-b border-zinc-300 dark:border-zinc-600 pl-5 pr-2 py-2 align-top text-sm font-medium text-zinc-700 dark:text-zinc-300 min-w-0 bg-white dark:bg-zinc-900">
                            <div class="flex items-center gap-2">
                                @if ($exercises->isNotEmpty())
                                    <button type="button" @click.stop="programExpanded[{{ $entry->id }}] = !programExpanded[{{ $entry->id }}]" class="shrink-0">
                                        <flux:icon.chevron-right class="size-4 transition-transform duration-200"
                                            ::class="programExpanded[{{ $entry->id }}] && 'rotate-90'" />
                                    </button>
                                @endif
                                @if ($categoryColor)
                                    <span class="w-2 h-2 rounded-full shrink-0"
                                        style="{{ \Coda\Cms\Support\ColorPalette::solid($categoryColor) }}"></span>
                                @endif
                                <div class="flex flex-1 min-w-0 items-center gap-2">
                                    <button type="button" wire:click="navigateToPlan({{ $entry->id }})"
                                        class="flex-1 min-w-0 text-left hover:underline">
                                        <span class="block truncate">{{ $entry->program->name }}</span>
                                    </button>
                                    @if ($entry->isArchived())
                                        <flux:badge size="sm" color="zinc">{{ __('Archived') }}</flux:badge>
                                    @endif
                                </div>
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="xs" icon="ellipsis" class="ml-auto shrink-0 !p-1" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" wire:click="openEditProgram({{ $entry->id }})">
                                            {{ __('Edit') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="copy" wire:click="openDuplicateProgram({{ $entry->id }})">
                                            {{ __('Duplicate') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                            @if ($exercises->isNotEmpty())
                                <div x-show="programExpanded[{{ $entry->id }}]" x-cloak class="mt-1 ml-10 flex min-w-0 flex-col gap-1">
                                    @foreach ($exercises as $exercise)
                                        <label wire:key="exercise-check-{{ $exercise->pivot->id }}" class="flex min-w-0 items-start gap-2 text-sm text-zinc-500 dark:text-zinc-400 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                wire:click="toggleExerciseDisabled({{ $exercise->pivot->id }}, {{ $entry->exercise_program_id }})"
                                                @if (!$this->isExerciseDisabled($exercise->pivot->id, $entry->program)) checked @endif
                                                class="mt-0.5 shrink-0 rounded border-zinc-300 dark:border-zinc-600 text-zinc-800 dark:text-white focus:ring-zinc-500 dark:bg-zinc-700"
                                            />
                                            <span class="min-w-0 whitespace-normal break-words leading-tight">{{ $exercise->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <template x-for="(day, dayIdx) in days" :key="'p-{{ $entry->id }}-' + day.date">
                            <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0.5 cursor-pointer hover:brightness-95 dark:hover:brightness-125"
                                style="{{ $dayCellStyle }}"
                                :class="[
                                    blockDayMap[dayIdx] ? '{{ $categoryBlockBgClass }}' : (day.oddWeek ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : ''),
                                    (endIdx !== null ? (dayIdx >= Math.min(anchorIdx, endIdx) && dayIdx <= Math.max(anchorIdx, endIdx)) : anchorIdx === dayIdx) && 'ring ring-inset ring-black dark:ring-white'
                                ]">
                                @if ($this->userId !== null)
                                    <button x-show="getCellCount({{ $entry->id }}, day.date) > 0"
                                        x-cloak
                                        type="button"
                                        @click.stop="let _t = getCellTime({{ $entry->id }}, day.date); _t ? $wire.editWeekSlot({{ $entry->id }}, day.date, _t) : $wire.openProgramSlot({{ $entry->id }}, day.date)"
                                        class="status-bar relative w-full aspect-square flex items-center justify-center text-[10px] font-medium text-white rounded-sm cursor-pointer overflow-hidden {{ $colorClass ?: 'bg-emerald-400/80 dark:bg-emerald-500/60' }}"
                                        :style="getCellStatusStyle({{ $entry->id }}, day.date)"
                                        :title="getCellStatusLabel({{ $entry->id }}, day.date)"
                                        x-text="getCellSession({{ $entry->id }}, day.date) || getCellCount({{ $entry->id }}, day.date)">
                                    </button>
                                    <div x-show="cellDataLoaded && getCellCount({{ $entry->id }}, day.date) === 0"
                                        x-cloak
                                        @click="$wire.openProgramSlot({{ $entry->id }}, day.date)"
                                        class="aspect-square flex items-center justify-center cursor-pointer group/empty">
                                        <svg class="size-3 text-zinc-400 dark:text-zinc-500 opacity-0 group-hover/empty:opacity-100 transition-opacity" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    </div>
                                @else
                                    <button x-show="getCellCount({{ $entry->id }}, day.date) > 0"
                                        x-cloak
                                        type="button"
                                        @click.stop="openPopover($event.currentTarget, {{ $entry->id }}, day.date, '{{ $categoryColor }}')"
                                        class="status-bar w-full aspect-square flex items-center justify-center text-[10px] font-medium text-white rounded-sm cursor-pointer {{ $colorClass ?: 'bg-emerald-400/80 dark:bg-emerald-500/60' }}"
                                        :style="getCellStatusStyle({{ $entry->id }}, day.date)"
                                        :title="getCellStatusLabel({{ $entry->id }}, day.date)"
                                        x-text="getCellCount({{ $entry->id }}, day.date)">
                                    </button>
                                    <div x-show="cellDataLoaded && getCellCount({{ $entry->id }}, day.date) === 0"
                                        x-cloak
                                        @click="$wire.openProgramSlot({{ $entry->id }}, day.date)"
                                        class="aspect-square flex items-center justify-center cursor-pointer group/empty">
                                        <svg class="size-3 text-zinc-400 dark:text-zinc-500 opacity-0 group-hover/empty:opacity-100 transition-opacity" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    </div>
                                @endif
                            </td>
                        </template>
                    </tr>
                @endforeach
                <template x-teleport="body">
                    <div x-show="contextMenu"
                         x-cloak
                         @click.outside="clearSelection()"
                         class="fixed z-50 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 min-w-[160px]"
                         :style="'left: ' + contextMenuX + 'px; top: ' + contextMenuY + 'px'">
                        <button type="button"
                                @click="performAction()"
                                class="w-full text-left px-3 py-1.5 text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700">
                            {{ __('Create Block') }}
                        </button>
                    </div>
                </template>
            </tbody>
        @endforeach
    </table>
    <template x-teleport="body">
        <div x-show="open"
             x-cloak
             id="calendar-slot-popover"
             class="fixed z-50 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 p-2 min-w-[10rem] max-w-[16rem]"
             :style="'left: ' + x + 'px; top: ' + y + 'px; transform: translateX(-50%)' + (_above ? ' translateY(-100%)' : '')">
            <div class="flex flex-col gap-1.5">
                <template x-if="loading">
                    <div class="flex items-center justify-center py-2">
                        <div class="h-4 w-4 animate-spin rounded-full border-2 border-zinc-300 border-t-zinc-600"></div>
                    </div>
                </template>
                <template x-if="slotDetails && !loading">
                    <template x-for="slot in slotDetails" :key="slot.time">
                        <x-training.calendar-slot-card
                            type="button"
                            @click="editSlot(slot.time)"
                            color-expr="color"
                            time-expr="slot.time"
                            name-expr="(slot.names || []).join(', ')"
                            status-color-expr="slot.statusColor"
                        />
                    </template>
                </template>
            </div>
        </div>
    </template>
</div>

<livewire:training.week-slot-form />

<livewire:training.exercise-program-form-modal
    name="edit-program"
    :title="__('Edit Exercise Program')"
    :flyout="true"
    maxWidth="max-w-lg"
    :showDelete="true"
    :excludeFields="['owner_id', 'internalTags']"
/>

<x-cms::confirm-modal
    name="confirm-delete-program"
    :heading="__('Remove program?')"
    :description="__('You\'re about to remove this program from the calendar. This action cannot be reversed.')"
    :confirmLabel="__('Delete')"
    action="deleteEditingTrainingProgram"
/>

<x-cms::confirm-modal
    name="confirm-delete-metric"
    :heading="__('Delete Metric?')"
    :description="__('You\'re about to delete this metric. This action cannot be reversed.')"
    :confirmLabel="__('Delete')"
    action="deleteMetricSubmission"
/>

</div>
