<div>
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <flux:button.group>
                <flux:button wire:click="navigate('prev')" icon="chevron-left" size="sm" />
                <flux:button wire:click="navigate('next')" icon="chevron-right" size="sm" />
            </flux:button.group>
            <flux:button wire:click="navigate('today')" variant="subtle" size="sm">today</flux:button>
        </div>

        <flux:heading size="lg">{{ $this->grid['title'] }}</flux:heading>

        <flux:button.group>
            @foreach (['month' => 'Month', 'week' => 'Week', 'day' => 'Day'] as $view => $label)
                @if ($currentView === $view)
                    <flux:button wire:click="setView('{{ $view }}')" size="sm" variant="filled">{{ $label }}</flux:button>
                @else
                    <flux:button wire:click="setView('{{ $view }}')" size="sm" variant="ghost">{{ $label }}</flux:button>
                @endif
            @endforeach
        </flux:button.group>
    </div>

    <div class="border-l border-t border-zinc-200 dark:border-white/10">
        <div class="grid" style="grid-template-columns: repeat({{ $this->grid['cols'] }}, minmax(0, 1fr))">
            @foreach ($this->grid['headers'] as $header)
                <div class="text-center text-xs font-medium text-zinc-500 dark:text-zinc-400
                            py-2 border-b border-r border-zinc-200 dark:border-white/10">
                    {{ $header }}
                </div>
            @endforeach
        </div>

        @foreach ($this->grid['rows'] as $rowIdx => $row)
            <div wire:key="row-{{ $rowIdx }}" class="grid" style="grid-template-columns: repeat({{ $this->grid['cols'] }}, minmax(0, 1fr))">
                @foreach ($row as $cell)
                    @php
                        $dateKey = $cell['date'];
                        $slots = $this->schedule[$dateKey] ?? [0 => [], 1 => []];
                    @endphp
                    <div wire:key="cell-{{ $dateKey }}"
                         class="border-b border-r border-zinc-200 dark:border-white/10 flex flex-col
                                {{ $cell['isToday'] ? 'bg-blue-500/5 dark:bg-blue-500/8' : '' }}
                                {{ !$cell['isCurrentMonth'] ? 'text-zinc-300 dark:text-zinc-600' : '' }}"
                         style="min-height: {{ $this->grid['cols'] === 1 ? '12rem' : '7rem' }}">

                        <div class="text-right p-1 text-sm font-medium">{{ $cell['day'] }}</div>

                        @foreach ([0 => 'AM', 1 => 'PM'] as $slotIdx => $slotLabel)
                            @php
                                $groupId = $dateKey . '-' . $slotIdx;
                                $programs = $slots[$slotIdx] ?? [];
                            @endphp
                            <div wire:key="slot-{{ $groupId }}"
                                 class="flex flex-1 border-t border-zinc-200/60 dark:border-white/5"
                                 x-data
                                 x-on:dragover.prevent="$el.classList.add('bg-blue-500/10')"
                                 x-on:dragleave="$el.classList.remove('bg-blue-500/10')"
                                 x-on:drop.prevent="
                                     $el.classList.remove('bg-blue-500/10');
                                     let id = $event.dataTransfer.getData('external-program-id');
                                     if (id) $wire.addProgram(parseInt(id), '{{ $dateKey }}', {{ $slotIdx }})
                                 ">
                                <div class="text-[10px] font-semibold uppercase w-8 shrink-0 flex items-center justify-center
                                            border-r border-zinc-200/60 dark:border-white/5
                                            {{ !count($programs) ? 'text-zinc-300 dark:text-zinc-600' : 'text-zinc-400' }}">
                                    {{ $slotLabel }}
                                </div>
                                <div class="p-1 flex-1"
                                     wire:sort="handleSort"
                                     wire:sort:group="calendar-programs"
                                     wire:sort:group-id="{{ $groupId }}">
                                    @foreach ($programs as $program)
                                        <div wire:key="prog-{{ $program['id'] }}-{{ $groupId }}"
                                             wire:sort:item="{{ $program['id'] }}"
                                             class="rounded px-1.5 py-0.5 mb-1 text-white text-[11px] leading-snug font-medium truncate cursor-grab"
                                             style="background-color: {{ $program['color'] }}"
                                             wire:click="onProgramClick({{ $program['id'] }}, '{{ $dateKey }}', {{ $slotIdx }})">
                                            {{ $program['name'] }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
