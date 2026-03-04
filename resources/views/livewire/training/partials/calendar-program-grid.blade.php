<div class="overflow-x-auto" x-data="{ clickTimer: null }">
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
        <tbody>
            @foreach ($this->programs as $entry)
                <tr wire:key="program-{{ $entry->id }}">
                    @php
                        $isInherited = $this->user !== '' && $entry->isGroupLevel();
                    @endphp
                    <td x-data="{ open: false }"
                        class="sticky left-0 z-10 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium text-zinc-700 dark:text-zinc-300 whitespace-nowrap align-top min-w-[180px] {{ $entry->program->programCategory?->color ? \Coda\Cms\Support\ColorPalette::lightOpaque($entry->program->programCategory->color) : 'bg-white dark:bg-zinc-900' }}">
                        <div class="flex items-center gap-2">
                            <div class="flex flex-col gap-0.5 flex-1 min-w-0">
                                @if ($isInherited)
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ $entry->program->name }}</span>
                                @else
                                    <button type="button" wire:click="openEditProgram({{ $entry->id }})"
                                        class="text-left hover:underline">
                                        {{ $entry->program->name }}
                                    </button>
                                    @if ($entry->sourcePlan)
                                        <flux:badge size="sm" variant="outline">{{ $entry->sourcePlan->name }}</flux:badge>
                                    @endif
                                @endif
                            </div>
                            @if ($entry->program->exercises->isNotEmpty())
                                <button
                                    @click="open = !open"
                                    class="shrink-0 ml-3 p-1.5 text-zinc-400 transition-colors"
                                >
                                    <flux:icon.chevron-down
                                        class="size-4 transition-transform duration-200"
                                        ::class="open && 'rotate-180'"
                                    />
                                </button>
                            @endif
                        </div>
                        @if ($entry->program->exercises->isNotEmpty())
                            <div x-show="open" x-collapse class="mt-1">
                                <div class="flex flex-col gap-0.5 pl-4">
                                    @foreach ($entry->program->exercises as $exercise)
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 font-normal">
                                            {{ $exercise->name }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </td>
                    @foreach ($this->days as $day)
                        @php
                            $dateKey = $entry->id . '-' . $day['date'];
                            $slotTime = $this->cellSlots[$dateKey] ?? null;
                            $label = match ($slotTime) {
                                '09:00' => 'AM',
                                '14:00' => 'PM',
                                null => null,
                                default => $slotTime,
                            };
                            $activeState = 'direct';
                            if ($slotTime !== null) {
                                $fullKey = $entry->id . '-' . $day['date'] . ' ' . $slotTime . ':00';
                                $activeState = $this->slotState[$fullKey] ?? 'direct';
                            }
                            $isEditing = $editingProgramId === $entry->id && $editingDate === $day['date'];
                        @endphp
                        @if ($isEditing)
                            <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 relative overflow-visible">
                                <div class="absolute left-0 top-0 z-50 min-w-[140px]"
                                    @click.outside="$wire.cancelEditing()"
                                    @keydown.escape.window="$wire.cancelEditing()">
                                    <flux:time-picker wire:model.live="editingCellTime" time-format="24-hour" :interval="15" />
                                </div>
                                <div class="aspect-square"></div>
                            </td>
                        @elseif ($label !== null && $activeState === 'inherited')
                            <td @click="if (clickTimer) { clearTimeout(clickTimer); clickTimer = null; return; } clickTimer = setTimeout(() => { $wire.cycleSlot({{ $entry->id }}, '{{ $day['date'] }}'); clickTimer = null; }, 200)"
                                @dblclick.prevent="clearTimeout(clickTimer); clickTimer = null; $wire.startEditingCell({{ $entry->id }}, '{{ $day['date'] }}')"
                                class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 bg-emerald-400/30 dark:bg-emerald-500/20 cursor-pointer">
                                <div class="aspect-square flex items-center justify-center text-[10px] font-medium text-emerald-700 dark:text-emerald-300">{{ $label }}</div>
                            </td>
                        @elseif ($label !== null)
                            <td @click="if (clickTimer) { clearTimeout(clickTimer); clickTimer = null; return; } clickTimer = setTimeout(() => { $wire.cycleSlot({{ $entry->id }}, '{{ $day['date'] }}'); clickTimer = null; }, 200)"
                                @dblclick.prevent="clearTimeout(clickTimer); clickTimer = null; $wire.startEditingCell({{ $entry->id }}, '{{ $day['date'] }}')"
                                class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 bg-emerald-400/60 dark:bg-emerald-500/40 cursor-pointer">
                                <div class="aspect-square flex items-center justify-center text-[10px] font-medium text-emerald-700 dark:text-emerald-300">{{ $label }}</div>
                            </td>
                        @else
                            <td @click="if (clickTimer) { clearTimeout(clickTimer); clickTimer = null; return; } clickTimer = setTimeout(() => { $wire.cycleSlot({{ $entry->id }}, '{{ $day['date'] }}'); clickTimer = null; }, 200)"
                                @dblclick.prevent="clearTimeout(clickTimer); clickTimer = null; $wire.startEditingCell({{ $entry->id }}, '{{ $day['date'] }}')"
                                class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 cursor-pointer {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '') }}">
                                <div class="aspect-square"></div>
                            </td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
