<div x-data="schedule_grid()" class="space-y-6">
    <x-section title="Weekly Schedule" class="!p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm table-fixed">
                <colgroup>
                    <col style="width: 8%" />
                    <col style="width: 4%" />
                    <col class="w-auto" />
                    <col class="w-auto" />
                    <col class="w-auto" />
                    <col class="w-auto" />
                    <col class="w-auto" />
                    <col class="w-auto" />
                    <col class="w-auto" />
                    <col style="width: 40px" />
                </colgroup>
                <thead>
                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                        <th class="border-b border-zinc-300 dark:border-zinc-600 px-3 py-2"></th>
                        <th class="border-b border-zinc-300 dark:border-zinc-600 px-3 py-2"></th>
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                            <th class="border-b border-zinc-300 dark:border-zinc-600 px-3 py-2">{{ $day }}</th>
                        @endforeach
                        <th class="border-b border-zinc-300 dark:border-zinc-600 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->schedule as $weekIndex => $week)
                        @php
                            $isLinked = $week['linkedToWeekId'] !== null;
                            $resolvedSlots = $this->getResolvedSlots($week);
                            $linkedToIndex = $isLinked
                                ? collect($this->schedule)->search(fn($w) => $w['id'] === $week['linkedToWeekId'])
                                : null;
                        @endphp
                        @foreach (['am' => 'AM', 'pm' => 'PM'] as $slotKey => $slotLabel)
                            <tr wire:key="week-{{ $week['id'] }}-{{ $slotKey }}"
                                class="{{ $isLinked ? 'bg-zinc-50 dark:bg-zinc-900/50' : '' }}">
                                @if ($slotKey === 'am')
                                    <td rowspan="2"
                                        class="border border-l-0 {{ $isLinked ? 'border-dashed border-zinc-400 dark:border-zinc-500' : 'border-zinc-300 dark:border-zinc-600' }} px-3 py-2 font-medium bg-zinc-50 dark:bg-zinc-800/50 text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <span>Week {{ $weekIndex + 1 }}</span>
                                            @if ($isLinked)
                                                <div class="flex items-center gap-1 text-xs text-zinc-500">
                                                    <flux:icon.lock class="size-3" />
                                                    <span>W{{ $linkedToIndex + 1 }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                                <td
                                    class="border {{ $isLinked ? 'border-dashed border-zinc-400 dark:border-zinc-500' : 'border-zinc-300 dark:border-zinc-600' }} px-2 py-2 text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800/50 text-center text-xs">
                                    {{ $slotLabel }}
                                </td>
                                @for ($dayIndex = 0; $dayIndex < 7; $dayIndex++)
                                    @php
                                        $programId = $resolvedSlots[$dayIndex][$slotKey]['programId'] ?? null;
                                        $programName = $programId ? $this->programOptions[$programId] ?? '?' : null;
                                        $programColor = $this->getProgramColor($programId);
                                        $cellId = $week['id'] . '-' . $dayIndex . '-' . $slotKey;
                                        $isEditingThisCell = $this->isEditing($week['id'], $dayIndex, $slotKey);
                                    @endphp
                                    <td class="border {{ $isLinked ? 'border-dashed border-zinc-400 dark:border-zinc-500' : 'border-zinc-300 dark:border-zinc-600' }} p-1 h-12 transition-colors duration-200"
                                        :class="{
                                            'bg-blue-100 dark:bg-blue-900/30': isDraggingOver === '{{ $cellId }}' &&
                                                !isDropDisallowed,
                                            'bg-red-100 dark:bg-red-900/30': isDraggingOver === '{{ $cellId }}' &&
                                                isDropDisallowed
                                        }"
                                        @if (!$isLinked && !$isEditingThisCell) data-week-id="{{ $week['id'] }}"
                                            data-day="{{ $dayIndex }}"
                                            data-slot="{{ $slotKey }}"
                                            data-program-id="{{ $programId }}"
                                            @dragover.prevent="handleDragOver($event)"
                                            @dragleave="handleDragLeave()"
                                            @drop.prevent="handleDrop($event)" @endif>
                                        @if ($isEditingThisCell)
                                            <div x-data x-init="$nextTick(() => $el.querySelector('button')?.click())" @click.outside="$wire.stopEditing()"
                                                @keydown.escape.window="$wire.stopEditing()" class="h-full w-full">
                                                <flux:select class="w-full" size="sm" searchable
                                                    placeholder="Select..." :value="$assigningProgramId"
                                                    wire:change="selectProgram($event.target.value)">
                                                    <flux:select.option value="">None</flux:select.option>
                                                    @foreach ($this->programOptions as $id => $name)
                                                        <flux:select.option value="{{ $id }}">
                                                            {{ $name }}</flux:select.option>
                                                    @endforeach
                                                </flux:select>
                                            </div>
                                        @elseif ($programId)
                                            <div class="h-full flex items-center justify-center rounded px-2 py-1 text-xs font-medium {{ $isLinked ? 'border-2 border-dashed opacity-70' : 'cursor-pointer' }}"
                                                style="background-color: {{ $this->getColorValue($programColor, $isLinked ? 200 : 500) }}; color: {{ $isLinked ? '#374151' : 'white' }}; {{ $isLinked ? 'border-color: ' . $this->getColorValue($programColor, 400) . ';' : '' }}"
                                                :class="{ 'opacity-50 scale-95': draggedCell === '{{ $cellId }}' }"
                                                @if (!$isLinked) draggable="true"
                                                    data-week-id="{{ $week['id'] }}"
                                                    data-day="{{ $dayIndex }}"
                                                    data-slot="{{ $slotKey }}"
                                                    data-program-id="{{ $programId }}"
                                                    @dragstart="handleDragStart($event)"
                                                    @dragend="handleDragEnd()"
                                                    wire:click="startEditing('{{ $week['id'] }}', {{ $dayIndex }}, '{{ $slotKey }}')" @endif>
                                                <span class="truncate">{{ $programName }}</span>
                                            </div>
                                        @else
                                            <div class="h-full flex items-center justify-center text-zinc-400 dark:text-zinc-600 rounded {{ $isLinked ? '' : 'cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800/50' }}"
                                                @if (!$isLinked) wire:click="startEditing('{{ $week['id'] }}', {{ $dayIndex }}, '{{ $slotKey }}')" @endif>
                                                @if (!$isLinked)
                                                    <flux:icon.plus class="size-4" />
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                @endfor
                                @if ($slotKey === 'am')
                                    <td rowspan="2"
                                        class="border border-r-0 {{ $isLinked ? 'border-dashed border-zinc-400 dark:border-zinc-500' : 'border-zinc-300 dark:border-zinc-600' }} bg-zinc-50 dark:bg-zinc-800/50 text-center">
                                        @if ($weekIndex > 0)
                                            <flux:dropdown>
                                                <flux:button variant="ghost" size="xs" icon="ellipsis" />
                                                <flux:menu>
                                                    @if ($isLinked)
                                                        <flux:menu.item wire:click="unlinkWeek('{{ $week['id'] }}')">
                                                            <flux:icon.link class="size-4 mr-2" />
                                                            Unlink
                                                        </flux:menu.item>
                                                    @else
                                                        <flux:menu.item
                                                            wire:click="openLinkModal('{{ $week['id'] }}')">
                                                            <flux:icon.link class="size-4 mr-2" />
                                                            Link to...
                                                        </flux:menu.item>
                                                    @endif
                                                    <flux:menu.item wire:click="openRemoveModal('{{ $week['id'] }}')"
                                                        variant="danger">
                                                        <flux:icon.trash-2 class="size-4 mr-2" />
                                                        Remove
                                                    </flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @endforeach
                    <tr>
                        <td colspan="10"
                            class="border border-dashed border-zinc-300 dark:border-zinc-600 border-b-0 border-l-0 border-r-0 px-3 py-4 bg-zinc-50/50 dark:bg-zinc-800/30 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-colors"
                            wire:click="addWeek">
                            <div class="flex items-center justify-center gap-2 text-zinc-400 dark:text-zinc-500">
                                <flux:icon.plus class="size-4" />
                                <span class="text-sm font-medium">Add Week</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </x-section>

    <flux:modal name="link-week" class="w-80">
        <div class="space-y-4">
            <flux:heading size="lg">Link Week</flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                Linked weeks inherit all program assignments from the source week.
            </flux:text>
            <flux:select wire:model="linkToWeekId" placeholder="Select source week...">
                <flux:select.option value="">None (unlink)</flux:select.option>
                @foreach ($this->availableWeeksForLinking as $availableWeek)
                    <flux:select.option value="{{ $availableWeek['id'] }}">{{ $availableWeek['label'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" wire:click="closeLinkModal">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="linkWeek">
                    Save
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="remove-week" class="w-80">
        <div class="space-y-4">
            <flux:heading size="lg">Remove Week</flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                Are you sure you want to remove this week? This action cannot be undone.
            </flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" wire:click="closeRemoveModal">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="confirmRemoveWeek">
                    Remove
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
