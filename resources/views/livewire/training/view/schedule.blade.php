<div x-data="schedule_grid()" class="flex gap-6 focus:outline-none">
    @if ($users->isNotEmpty())
        <x-section title="Schedules" class="w-64 shrink-0 sticky top-4 self-start">
            <div class="flex flex-col gap-1">
                <flux:button wire:click="selectUser(null)" variant="{{ $user === null ? 'primary' : 'ghost' }}"
                    class="justify-start">
                    <span class="flex-1 text-left">Default</span>
                </flux:button>

                <div class="mx-3">
                    <flux:separator class="my-2" variant="subtle" />
                </div>

                @foreach ($this->users as $userItem)
                    @php
                        $isSelected = $user === $userItem->id;
                        $hasCustom = ! $this->config->isUserScheduleLocked($userItem->id);
                    @endphp
                    <flux:button wire:key="user-btn-{{ $userItem->id }}" wire:click="selectUser({{ $userItem->id }})"
                        variant="{{ $isSelected ? 'primary' : 'ghost' }}" class="justify-start">
                        <span class="flex-1 text-left">{{ $userItem->name }}</span>
                        @if ($hasCustom && $isSelected)
                            <flux:badge size="sm" color="lime" class="!text-green-700">Custom</flux:badge>
                        @elseif ($hasCustom)
                            <flux:badge size="sm" color="lime">Custom</flux:badge>
                        @endif
                    </flux:button>
                @endforeach
            </div>
        </x-section>
    @endif

    <div class="flex-1 space-y-6">
        @if ($user === null && ! $exercisePlan->isTemplate())
            <flux:heading size="xl">Default Schedule</flux:heading>
        @elseif ($this->selectedUser)
            <div class="flex items-center justify-between">
                <flux:heading size="xl">{{ $this->selectedUser->name }}</flux:heading>
                @if ($this->hasCustomSchedule)
                    <flux:button wire:click="confirmResetSchedule"
                        variant="primary" size="sm" icon="rotate-ccw">
                        Reset
                    </flux:button>
                @endif
            </div>
        @endif

        @if ($users->isNotEmpty())
            <x-section title="Start Date" class="!py-3">
                <div class="flex items-end gap-3">
                    <flux:field class="flex-1">
                        <flux:select wire:model.live="startDate">
                            @foreach ($this->weekOptions as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                </div>
            </x-section>
        @endif

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
                                <th class="border-b border-zinc-300 dark:border-zinc-600 px-3 py-2">{{ $day }}
                                </th>
                            @endforeach
                            <th class="border-b border-zinc-300 dark:border-zinc-600 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->schedule as $weekIndex => $week)
                            @php
                                $isLinked = $week->linkedTo !== null;
                                $resolvedSlots = $this->getResolvedSlots($week);
                                $linkedToIndex = $isLinked
                                    ? $this->schedule->search(fn($w) => $w->id === $week->linkedTo)
                                    : null;
                                $isReadOnly = $isLinked;
                            @endphp
                            @foreach ([0 => 'AM', 1 => 'PM'] as $slotKey => $slotLabel)
                                <tr wire:key="week-{{ $week->id }}-{{ $slotKey }}-{{ $user ?? 'default' }}"
                                    class="{{ $isLinked ? 'bg-zinc-50 dark:bg-zinc-900/50' : '' }}">
                                    @if ($slotKey === 0)
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
                                            $slotData = $resolvedSlots[$dayIndex][$slotKey] ?? [];
                                            $slotPrograms = $slotData['programs'] ?? [];
                                            $hasPrograms = ! empty($slotPrograms);
                                            $cellId = $week->id . '-' . $dayIndex . '-' . $slotKey;
                                        @endphp
                                        <td class="border {{ $isLinked ? 'border-dashed border-zinc-400 dark:border-zinc-500' : 'border-zinc-300 dark:border-zinc-600' }} p-1 h-12 transition-colors duration-200"
                                            :class="{
                                                'bg-blue-100 dark:bg-blue-900/30': isDraggingOver === '{{ $cellId }}' &&
                                                    !isDropDisallowed,
                                                'bg-red-100 dark:bg-red-900/30': isDraggingOver === '{{ $cellId }}' &&
                                                    isDropDisallowed
                                            }"
                                            @if (!$isReadOnly) data-week-id="{{ $week->id }}"
                                                data-day="{{ $dayIndex }}"
                                                data-slot="{{ $slotKey }}"
                                                data-has-programs="{{ $hasPrograms ? 'true' : '' }}"
                                                @dragover.prevent="handleDragOver($event)"
                                                @dragleave="handleDragLeave()"
                                                @drop.prevent="handleDrop($event)" @endif>
                                            <div class="h-full flex flex-col items-stretch justify-center gap-0.5">
                                                @foreach ($slotPrograms as $programId)
                                                    @php
                                                        $programName = $this->programOptions[$programId] ?? '?';
                                                        $programColor = $this->getProgramColor($programId);
                                                        $programDragId = $cellId . '-' . $programId;
                                                    @endphp
                                                    @if (!$isReadOnly)
                                                        <div class="flex items-center justify-center rounded px-2 py-1 text-xs font-medium cursor-pointer border-y-2 border-transparent"
                                                            style="{{ \Coda\Cms\Support\ColorPalette::solid($programColor) }}"
                                                            :class="{
                                                                'opacity-50 scale-95': draggedProgram === '{{ $programDragId }}',
                                                                '!border-t-blue-500': dropTargetKey === '{{ $programDragId }}' && dropPosition === 'before',
                                                                '!border-b-blue-500': dropTargetKey === '{{ $programDragId }}' && dropPosition === 'after'
                                                            }"
                                                            draggable="true"
                                                            data-week-id="{{ $week->id }}"
                                                            data-day="{{ $dayIndex }}"
                                                            data-slot="{{ $slotKey }}"
                                                            data-program-id="{{ $programId }}"
                                                            @dragstart="handleDragStart($event)"
                                                            @dragend="handleDragEnd()"
                                                            wire:click="openEditProgramModal({{ $programId }}, '{{ $week->id }}', {{ $dayIndex }}, {{ $slotKey }})">
                                                            <span class="truncate">{{ $programName }}</span>
                                                        </div>
                                                    @else
                                                        <div class="flex items-center justify-center rounded px-2 py-1 text-xs font-medium"
                                                            style="{{ \Coda\Cms\Support\ColorPalette::solid($programColor) }}">
                                                            <span class="truncate">{{ $programName }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach
                                                @if (!$isReadOnly)
                                                    <div class="flex items-center justify-center text-zinc-400 dark:text-zinc-600">
                                                        <flux:dropdown>
                                                            <flux:button variant="ghost" size="xs" icon="plus"
                                                                class="!text-zinc-400 dark:!text-zinc-600 hover:!text-zinc-600 dark:hover:!text-zinc-400" />
                                                            <flux:menu>
                                                                <flux:menu.item icon="plus"
                                                                    wire:click="openCreateProgramModal('{{ $week->id }}', {{ $dayIndex }}, {{ $slotKey }})">
                                                                    Create
                                                                </flux:menu.item>
                                                                <flux:menu.item icon="link"
                                                                    wire:click="openLinkProgramModal('{{ $week->id }}', {{ $dayIndex }}, {{ $slotKey }})">
                                                                    Link
                                                                </flux:menu.item>
                                                            </flux:menu>
                                                        </flux:dropdown>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    @endfor
                                    @if ($slotKey === 0)
                                        <td rowspan="2"
                                            class="border border-r-0 {{ $isLinked ? 'border-dashed border-zinc-400 dark:border-zinc-500' : 'border-zinc-300 dark:border-zinc-600' }} bg-zinc-50 dark:bg-zinc-800/50 text-center">
                                            @if ($weekIndex > 0)
                                                <flux:dropdown>
                                                    <flux:button variant="ghost" size="xs" icon="ellipsis" />
                                                    <flux:menu>
                                                        @if ($isLinked)
                                                            <flux:menu.item
                                                                wire:click="$dispatch('schedule-event', { type: 'unlink-week', data: { weekId: '{{ $week->id }}' } })">
                                                                <flux:icon.link class="size-4 mr-2" />
                                                                Unlink
                                                            </flux:menu.item>
                                                        @else
                                                            <flux:menu.item
                                                                wire:click="openLinkModal('{{ $week->id }}')">
                                                                <flux:icon.link class="size-4 mr-2" />
                                                                Link to...
                                                            </flux:menu.item>
                                                        @endif
                                                        <flux:menu.item
                                                            wire:click="openRemoveModal('{{ $week->id }}')"
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
                                wire:click="$dispatch('schedule-event', { type: 'add-week' })">
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
    </div>

    <flux:modal name="remove-week" class="w-80">
        <div class="space-y-4">
            <flux:heading size="lg">Remove Week</flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                Are you sure you want to remove this week? This action cannot be undone.
            </flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="confirmRemoveWeek">
                    Remove
                </flux:button>
            </div>
        </div>
    </flux:modal>

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
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="confirmLinkWeek">
                    Save
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="link-program" flyout class="w-80">
        <div class="space-y-4">
            <flux:heading size="lg">Link Program</flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                Select an existing program to place in this slot.
            </flux:text>
            @php $filteredOptions = $this->availableProgramOptionsForSlot(); @endphp
            <flux:select wire:model="linkingProgramId" placeholder="Select a program...">
                @foreach ($filteredOptions as $group => $options)
                    @if (is_array($options))
                        <flux:select.option disabled>— {{ $group }} —</flux:select.option>
                        @foreach ($options as $id => $name)
                            <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                        @endforeach
                    @endif
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="confirmLinkProgram">
                    Link
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="add-program" flyout class="max-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">
                {{ $editingProgramId ? 'Edit Program' : 'Create Program' }}
            </flux:heading>
            <form wire:submit="saveProgram" class="space-y-4">
                @foreach ($this->fieldsets as $item)
                    <x-cms::form.fieldset
                        :fieldset="$item"
                        :prefix="$item->prefix ?? 'data'"
                        :showLegend="count($this->fieldsets) > 1"
                    />
                @endforeach
                <div class="flex gap-2 pt-4">
                    <flux:button type="submit" variant="primary" class="flex-1">
                        {{ $editingProgramId ? 'Save' : 'Create' }}
                    </flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    @if ($editingProgramId)
                        <flux:button variant="ghost" icon="trash-2" wire:click="removeFromSchedule"
                            wire:confirm="Are you sure you want to remove this program from the slot?"
                            class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                    @endif
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="reset-schedule" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Reset Schedule?</flux:heading>
                <flux:text class="mt-2">
                    This will remove all user-specific programs and reset to the default schedule.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="resetSchedule">
                    Reset
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
