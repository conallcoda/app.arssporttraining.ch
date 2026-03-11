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
                <tr class="h-[40px]">
                    <td class="sticky left-0 z-10 bg-white dark:bg-zinc-900 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-1 font-semibold text-zinc-700 dark:text-zinc-300 whitespace-nowrap min-w-[180px]">
                        {{ __('Focus') }}
                    </td>
                    @foreach ($this->days as $dayIdx => $day)
                        @if (array_key_exists($dayIdx, $this->focusNotes))
                            @php
                                $focusCell = $this->focusNotes[$dayIdx];
                            @endphp
                            @if ($focusCell !== null)
                                <td wire:click="editFocusNote({{ $focusCell['id'] }})"
                                    colspan="{{ $focusCell['colspan'] }}"
                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-1 cursor-pointer h-[1px]">
                                    <div class="rounded-sm bg-amber-400/80 dark:bg-amber-500/60 flex items-center justify-center h-full px-1 min-h-0">
                                        <span class="text-[10px] font-medium text-white truncate">{{ $focusCell['note'] }}</span>
                                    </div>
                                </td>
                            @endif
                        @else
                            <td wire:click="openFocusNote('{{ $day['date'] }}')"
                                class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 cursor-pointer {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '') }}">
                            </td>
                        @endif
                    @endforeach
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
            <tbody x-data="{ expanded: true }" wire:key="category-{{ $categoryId }}">
                <tr class="cursor-pointer" @click="expanded = !expanded">
                    <td
                        class="sticky left-0 z-10 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 font-semibold text-zinc-700 dark:text-zinc-300 whitespace-nowrap min-w-[180px] {{ $categoryColorClass }}">
                        <div class="flex items-center gap-2">
                            <flux:icon.chevron-right class="size-4 transition-transform duration-200"
                                ::class="expanded && 'rotate-90'" />
                            <span>{{ $category?->name ?? 'Uncategorized' }}</span>
                            <span
                                class="text-xs font-normal text-zinc-500 dark:text-zinc-200">({{ $groupEntries->count() }})</span>
                        </div>
                    </td>
                    @foreach ($this->days as $day)
                        @php
                            $daySlotCount = 0;
                            foreach ($groupEntries as $ge) {
                                $slots = $this->programCellSlots[$ge->id . '-' . $day['date']] ?? null;
                                if ($slots !== null) {
                                    $daySlotCount++;
                                }
                            }
                        @endphp
                        @if ($daySlotCount > 0)
                            <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-1">
                                <div class="aspect-square rounded-sm {{ $categoryColorClass }}">
                                </div>
                            </td>
                        @else
                            <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0">
                                <div class="aspect-square"></div>
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
                    @endphp
                    <tr wire:key="program-{{ $entry->id }}" x-show="expanded" x-cloak>
                        <td
                            class="sticky left-0 z-10 border-r border-b border-zinc-300 dark:border-zinc-600 pl-7 pr-3 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 whitespace-nowrap min-w-[180px] bg-white dark:bg-zinc-900">
                            <div class="flex items-center gap-2">
                                @if ($categoryColor)
                                    <span class="w-2 h-2 rounded-full shrink-0"
                                        style="{{ \Coda\Cms\Support\ColorPalette::solid($categoryColor) }}"></span>
                                @endif
                                <button type="button" wire:click="openEditProgram({{ $entry->id }})"
                                    class="text-left hover:underline">
                                    {{ $entry->program->name }}
                                </button>
                            </div>
                        </td>
                        @foreach ($this->days as $day)
                            @php
                                $dateKey = $entry->id . '-' . $day['date'];
                                $slotTimes = $this->programCellSlots[$dateKey] ?? null;
                                $slotCount = $slotTimes !== null ? count($slotTimes) : 0;
                            @endphp
                            @if ($slotCount > 0)
                                <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-1">
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
                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 cursor-pointer {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '') }}">
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
