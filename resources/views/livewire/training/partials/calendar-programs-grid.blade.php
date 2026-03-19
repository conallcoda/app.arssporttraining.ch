<div class="overflow-x-auto">
    <table class="border-separate border-spacing-0 text-sm border-t border-l border-zinc-300 dark:border-zinc-600">
        <thead>
            <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                <th rowspan="3"
                    class="sticky left-0 z-10 bg-zinc-100 dark:bg-zinc-800 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 text-left min-w-[180px]">
                </th>
                @foreach ($this->months as $month)
                    <th colspan="{{ $month['colspan'] }}"
                        class="border-r border-b border-zinc-300 dark:border-zinc-600 px-2 py-1.5 text-center text-xs font-semibold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800">
                        {{ $month['label'] }}
                    </th>
                @endforeach
            </tr>
            <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                @foreach ($this->weeks as $week)
                    <th colspan="{{ $week['colspan'] }}"
                        class="border-r border-b border-zinc-300 dark:border-zinc-600 px-1 py-1 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400 {{ $week['week'] % 2 !== 0 ? 'bg-zinc-100/50 dark:bg-zinc-700/20' : '' }}">
                        W{{ $week['week'] }}
                    </th>
                @endforeach
            </tr>
            <tr class="bg-zinc-100 dark:bg-zinc-800">
                @foreach ($this->days as $day)
                    <th
                        class="border-r border-b border-zinc-300 dark:border-zinc-600 px-1 py-2 text-center min-w-[40px] {{ $day['isToday'] ? 'bg-blue-100 dark:bg-blue-900/30' : ($day['oddWeek'] ? 'bg-zinc-100/50 dark:bg-zinc-700/20' : '') }}">
                        <div class="text-[10px] leading-tight">{{ $day['label'] }}</div>
                        <div class="font-medium text-xs">{{ $day['day'] }}</div>
                    </th>
                @endforeach
            </tr>
        </thead>
        @if ($this->programs->isNotEmpty())
            <tbody>
                <tr>
                    <td class="sticky left-0 z-20 bg-zinc-100 dark:bg-zinc-800 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-1 min-w-[180px] text-xs font-medium text-zinc-500 dark:text-zinc-400">
                        {{ __('Notes') }}
                    </td>
                    <td colspan="{{ count($this->days) }}"
                        class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 relative"
                        style="height: {{ max(1, $this->allBlocks['laneCount']) * 28 + 8 }}px">
                        <div class="absolute inset-0 flex">
                            @foreach ($this->days as $dayIdx => $day)
                                <div wire:click="openBlock('{{ $day['date'] }}')"
                                     wire:key="block-bg-{{ $dayIdx }}"
                                     class="flex-1 cursor-pointer h-full border-r border-zinc-300 dark:border-zinc-600 last:border-r-0 {{ $day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '' }}">
                                </div>
                            @endforeach
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
            <tbody x-data="{ expanded: $persist(false).as('cal-cat-{{ $categoryId }}'), programExpanded: {} }" wire:key="category-{{ $categoryId }}">
                {{-- Block labels row (only if blocks exist) --}}
                @if ($hasBlocks)
                    <tr>
                        <td @click="expanded = !expanded"
                            rowspan="{{ $labelRowspan }}"
                            class="sticky left-0 z-10 cursor-pointer border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 font-semibold text-zinc-700 dark:text-zinc-300 whitespace-nowrap min-w-[180px] {{ $categoryColorClass }}">
                            <div class="flex items-center gap-2">
                                <flux:icon.chevron-right class="size-4 transition-transform duration-200"
                                    ::class="expanded && 'rotate-90'" />
                                <span>{{ $category?->name ?? 'Uncategorized' }}</span>
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
                                @if ($this->user === '')
                                    <td wire:click="editBlock({{ $block['id'] }})"
                                        colspan="{{ $span }}"
                                        class="border-r border-b border-zinc-300 dark:border-zinc-600 px-1 cursor-pointer {{ $categoryBlockBgClass }}"
                                        style="height: 22px">
                                        <span class="text-xs font-medium truncate block text-center text-zinc-600 dark:text-zinc-300">{{ $block['note'] }}</span>
                                    </td>
                                @else
                                    <td colspan="{{ $span }}"
                                        class="border-r border-b border-zinc-300 dark:border-zinc-600 px-1 {{ $categoryBlockBgClass }}"
                                        style="height: 22px">
                                        <span class="text-xs font-medium truncate block text-center text-zinc-600 dark:text-zinc-300">{{ $block['note'] }}</span>
                                    </td>
                                @endif
                            @else
                                <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0" style="height: 22px"></td>
                            @endif
                        @endforeach
                    </tr>
                @endif

                {{-- Indicator squares row --}}
                <tr>
                    @if (!$hasBlocks)
                        <td @click="expanded = !expanded"
                            class="sticky left-0 z-10 cursor-pointer border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 font-semibold text-zinc-700 dark:text-zinc-300 whitespace-nowrap min-w-[180px] {{ $categoryColorClass }}">
                            <div class="flex items-center gap-2">
                                <flux:icon.chevron-right class="size-4 transition-transform duration-200"
                                    ::class="expanded && 'rotate-90'" />
                                <span>{{ $category?->name ?? 'Uncategorized' }}</span>
                                <span class="text-xs font-normal text-zinc-500 dark:text-zinc-200">({{ $groupEntries->count() }})</span>
                            </div>
                        </td>
                    @endif
                    @foreach ($this->days as $dayIdx => $day)
                        @php
                            $daySlotCount = 0;
                            foreach ($groupEntries as $ge) {
                                $slots = $this->programCellSlots[$ge->id . '-' . $day['date']] ?? null;
                                if ($slots !== null) {
                                    $daySlotCount++;
                                }
                            }
                            $inBlock = isset($blockDayMap[$dayIdx]);
                            $blockId = $inBlock ? $blockDayMap[$dayIdx]['id'] : null;
                            $blockNote = $inBlock ? $blockDayMap[$dayIdx]['note'] : null;
                            $bgClass = $inBlock ? $categoryBlockBgClass : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '');
                        @endphp
                        @if ($this->user === '')
                            <td wire:click="{{ $inBlock ? 'editBlock(' . $blockId . ')' : 'openCategoryBlock(\'' . $day['date'] . '\', ' . $categoryId . ')' }}"
                                @if ($inBlock) title="{{ $blockNote }}" @endif
                                class="border-r border-b border-zinc-300 dark:border-zinc-600 p-1 cursor-pointer hover:bg-zinc-100/50 dark:hover:bg-zinc-500/10 {{ $bgClass }}">
                                @if ($daySlotCount > 0)
                                    <div class="aspect-square rounded-sm {{ $categoryColorClass }}"></div>
                                @else
                                    <div class="aspect-square"></div>
                                @endif
                            </td>
                        @else
                            <td @if ($inBlock) title="{{ $blockNote }}" @endif
                                class="border-r border-b border-zinc-300 dark:border-zinc-600 p-1 hover:bg-zinc-100/50 dark:hover:bg-zinc-500/10 {{ $bgClass }}">
                                @if ($daySlotCount > 0)
                                    <div class="aspect-square rounded-sm {{ $categoryColorClass }}"></div>
                                @else
                                    <div class="aspect-square"></div>
                                @endif
                            </td>
                        @endif
                    @endforeach
                </tr>

                @foreach ($groupEntries as $entry)
                    @php
                        $categoryColor = $entry->program->exerciseCategory?->color;
                        $colorClass = $categoryColor
                            ? \Coda\Cms\Support\ColorPalette::solidClasses($categoryColor)
                            : '';
                        $exercises = $entry->program->exercises->sortBy('pivot.sort');
                    @endphp
                    <tr wire:key="program-{{ $entry->id }}" x-show="expanded" x-cloak class="group/program">
                        <td
                            class="sticky left-0 z-10 border-r border-b border-zinc-300 dark:border-zinc-600 pl-7 pr-3 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 whitespace-nowrap min-w-[180px] bg-white dark:bg-zinc-900">
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
                                <button type="button" wire:click="navigateToPlan({{ $entry->id }})"
                                    class="text-left hover:underline">
                                    {{ $entry->program->name }}
                                </button>
                                <button type="button" wire:click.stop="openEditProgram({{ $entry->id }})"
                                    class="opacity-0 group-hover/program:opacity-100 transition-opacity shrink-0 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                                    <flux:icon.pencil class="size-3.5" />
                                </button>
                            </div>
                            @if ($exercises->isNotEmpty())
                                <div x-show="programExpanded[{{ $entry->id }}]" x-cloak class="mt-1 ml-10 flex flex-col gap-0.5">
                                    @foreach ($exercises as $exercise)
                                        <button type="button" wire:click="openExerciseSettings({{ $exercise->id }}, {{ $entry->exercise_program_id }}, {{ $entry->id }})" class="text-sm text-zinc-500 dark:text-zinc-400 text-left hover:underline">{{ $exercise->name }}</button>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        @foreach ($this->days as $dayIdx => $day)
                            @php
                                $dateKey = $entry->id . '-' . $day['date'];
                                $slotTimes = $this->programCellSlots[$dateKey] ?? null;
                                $slotCount = $slotTimes !== null ? count($slotTimes) : 0;
                                $programInBlock = isset($blockDayMap[$dayIdx]);
                                $programBlockNote = $programInBlock ? $blockDayMap[$dayIdx]['note'] : null;
                                $programBgClass = $programInBlock ? $categoryBlockBgClass : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '');
                            @endphp
                            @if ($slotCount > 0)
                                <td @if ($programInBlock) title="{{ $programBlockNote }}" @endif
                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-1 hover:bg-zinc-100/50 dark:hover:bg-zinc-500/10 {{ $programBgClass }}">
                                    <flux:dropdown position="bottom center">
                                        <button type="button"
                                            class="w-full aspect-square flex items-center justify-center text-[10px] font-medium text-white rounded-sm cursor-pointer {{ $colorClass ?: 'bg-emerald-400/80 dark:bg-emerald-500/60' }}">
                                            {{ $slotCount }}
                                        </button>
                                        <flux:popover class="min-w-[10rem] p-2">
                                            <div class="flex flex-col gap-1.5">
                                                @foreach ($slotTimes as $time => $athletes)
                                                    @if ($categoryColor)
                                                        <button type="button"
                                                            wire:click="editWeekSlot({{ $entry->id }}, '{{ $day['date'] }}', '{{ $time }}')"
                                                            class="flex flex-col px-2 py-1.5 rounded-lg text-left cursor-pointer hover:opacity-80 transition-opacity"
                                                            style="{{ \Coda\Cms\Support\ColorPalette::solid($categoryColor) }}">
                                                            <span class="text-[10px] opacity-80">{{ $time }}</span>
                                                            <span class="text-[10px] opacity-80 truncate">{{ implode(', ', $athletes) }}</span>
                                                        </button>
                                                    @else
                                                        <button type="button"
                                                            wire:click="editWeekSlot({{ $entry->id }}, '{{ $day['date'] }}', '{{ $time }}')"
                                                            class="flex flex-col px-2 py-1.5 rounded-lg text-left cursor-pointer hover:opacity-80 transition-opacity bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                                            <span class="text-[10px] opacity-60">{{ $time }}</span>
                                                            <span class="text-[10px] opacity-60 truncate">{{ implode(', ', $athletes) }}</span>
                                                        </button>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </flux:popover>
                                    </flux:dropdown>
                                </td>
                            @else
                                <td wire:click="openProgramSlot({{ $entry->id }}, '{{ $day['date'] }}')"
                                    @if ($programInBlock) title="{{ $programBlockNote }}" @endif
                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 cursor-pointer hover:bg-zinc-100/50 dark:hover:bg-zinc-500/10 {{ $programBgClass }}">
                                    <div class="aspect-square"></div>
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        @endforeach
    </table>
</div>
