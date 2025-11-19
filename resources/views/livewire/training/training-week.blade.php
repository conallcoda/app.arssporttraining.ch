<div class="space-y-4"
     x-data="{
        draggedSessionUuid: null,
        draggedFromDay: null,
        draggedFromSlot: null,
        isDraggingOver: null,

        startDrag(sessionUuid, day, slot) {
            this.draggedSessionUuid = sessionUuid;
            this.draggedFromDay = day;
            this.draggedFromSlot = slot;
        },

        dragOver(day, slot) {
            this.isDraggingOver = day + '-' + slot;
        },

        dragLeave() {
            this.isDraggingOver = null;
        },

        drop(targetSessionUuid, targetDay, targetSlot) {
            if (this.draggedSessionUuid && (this.draggedFromDay !== targetDay || this.draggedFromSlot !== targetSlot)) {
                if (targetSessionUuid) {
                    $wire.swapSessions(this.draggedSessionUuid, targetSessionUuid);
                } else {
                    $wire.moveSession(this.draggedSessionUuid, targetDay, targetSlot);
                }
            }
            this.draggedSessionUuid = null;
            this.draggedFromDay = null;
            this.draggedFromSlot = null;
            this.isDraggingOver = null;
        }
     }">
    @php
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $timePeriods = ['Morning', 'Afternoon'];

        $sessionsByDayAndSequence = [];
        foreach ($week->children as $session) {
            $day = $session->data->day;
            $slot = $session->data->slot;
            $sessionsByDayAndSequence[$day][$slot] = $session;
        }

        $categoriesById = $categories->keyBy('id');
    @endphp

    {{-- Week Title --}}
    <div class="flex items-center justify-between">
        <flux:heading size="md">Week {{ $week->sequence + 1 }}</flux:heading>
    </div>

    {{-- Week Grid --}}
    <div class="overflow-x-auto">
        <table class="w-full border-collapse table-fixed">
            <thead>
                <tr>
                    <th
                        class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-2 text-left text-sm font-semibold w-24">
                        Time
                    </th>
                    @foreach ($days as $day)
                        <th
                            class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-2 text-center text-sm font-semibold"
                            style="width: calc((100% - 6rem) / 7);">
                            {{ $day }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($timePeriods as $sequenceIndex => $period)
                    <tr>
                        <td
                            class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-2 text-sm font-medium">
                            {{ $period }}
                        </td>
                        @foreach ($days as $dayIndex => $day)
                            @php
                                $session = $sessionsByDayAndSequence[$dayIndex][$sequenceIndex] ?? null;
                                $sessionCategory = $session && $session->data->category ? ($categoriesById[$session->data->category] ?? null) : null;
                                $cellKey = $dayIndex . '-' . $sequenceIndex;
                            @endphp
                            <td
                                class="border border-zinc-200 dark:border-zinc-700 p-2 h-24 align-top transition-all duration-200"
                                :class="isDraggingOver === '{{ $cellKey }}' ? 'bg-blue-100 dark:bg-blue-900/30 scale-105' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50'"
                                @dragover.prevent="dragOver({{ $dayIndex }}, {{ $sequenceIndex }})"
                                @dragleave="dragLeave()"
                                @drop.prevent="drop('{{ $session?->uuid }}', {{ $dayIndex }}, {{ $sequenceIndex }})">

                                @if($sessionCategory)
                                    <div class="h-full flex items-center justify-center rounded px-2 py-1 cursor-move group relative transition-transform duration-200"
                                         :class="draggedSessionUuid === '{{ $session->uuid }}' ? 'opacity-50 scale-95' : ''"
                                         style="background-color: {{ $this->getColorValue($sessionCategory->background_color) }}; color: {{ $this->getColorValue($sessionCategory->text_color) }};"
                                         draggable="true"
                                         @dragstart="startDrag('{{ $session->uuid }}', {{ $dayIndex }}, {{ $sequenceIndex }})"
                                         @dragend="draggedSessionUuid = null; isDraggingOver = null"
                                         @dblclick="$wire.openSessionModal('{{ $session->uuid }}', {{ $dayIndex }}, {{ $sequenceIndex }})">
                                        <span class="text-sm font-medium">{{ $sessionCategory->name }}</span>
                                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 rounded transition-colors pointer-events-none"></div>
                                    </div>
                                @else
                                    <div class="h-full flex items-center justify-center text-zinc-400 dark:text-zinc-600 cursor-pointer"
                                         wire:click="openSessionModal(null, {{ $dayIndex }}, {{ $sequenceIndex }})">
                                        <x-lucide-plus class="w-5 h-5" />
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <flux:modal name="session-modal" wire:model="showSessionModal" class="min-w-md">
        <form wire:submit="saveSession" class="space-y-6">
            @php
                $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $slotNames = ['Morning', 'Afternoon'];
            @endphp

            <div>
                <flux:heading size="lg">{{ $editingSessionUuid ? 'Edit Session' : 'Add Session' }}</flux:heading>
            </div>

            <flux:field>
                <flux:label>Day</flux:label>
                <flux:input value="{{ $dayNames[$sessionDay ?? 0] }}" disabled />
            </flux:field>

            <flux:field>
                <flux:label>Time Slot</flux:label>
                <flux:input value="{{ $slotNames[$sessionSlot ?? 0] }}" disabled />
            </flux:field>

            <flux:field>
                <flux:select wire:model="sessionCategory" label="Session Category" placeholder="Select a category">
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="sessionCategory" />
            </flux:field>

            <flux:field>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:label>Exercises</flux:label>
                        <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addExercise">Add Exercise</flux:button>
                    </div>

                    @if(count($sessionExercises) > 0)
                        <div class="space-y-2">
                            @foreach($sessionExercises as $index => $exerciseId)
                                @php
                                    $isFirst = $index === 0;
                                    $isLast = $index === count($sessionExercises) - 1;
                                @endphp
                                <div class="flex items-center gap-2" wire:key="exercise-{{ $index }}">
                                    <div class="flex-1">
                                        <flux:select
                                            wire:model="sessionExercises.{{ $index }}"
                                            placeholder="Select an exercise"
                                            searchable>
                                            <option value="">Select an exercise</option>
                                            @foreach(\App\Models\Exercise\Exercise::orderBy('name')->get() as $exercise)
                                                <option value="{{ $exercise->id }}">{{ $exercise->name }}</option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                    <div class="flex gap-1">
                                        <button
                                            type="button"
                                            wire:click="moveExerciseUp({{ $index }})"
                                            {{ $isFirst ? 'disabled' : '' }}
                                            class="inline-flex items-center justify-center gap-2 px-2 py-1.5 text-sm font-medium rounded transition-colors {{ $isFirst ? 'opacity-40 cursor-not-allowed text-zinc-400' : 'text-zinc-700 hover:bg-zinc-100' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="moveExerciseDown({{ $index }})"
                                            {{ $isLast ? 'disabled' : '' }}
                                            class="inline-flex items-center justify-center gap-2 px-2 py-1.5 text-sm font-medium rounded transition-colors {{ $isLast ? 'opacity-40 cursor-not-allowed text-zinc-400' : 'text-zinc-700 hover:bg-zinc-100' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="removeExercise({{ $index }})" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-zinc-500">No exercises added yet.</p>
                    @endif
                </div>
            </flux:field>

            <div class="flex gap-2 justify-between">
                <div>
                    @if($editingSessionUuid)
                        <flux:button type="button" variant="danger" wire:click="deleteSession('{{ $editingSessionUuid }}')" icon="trash">Delete</flux:button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <flux:button type="button" variant="ghost" wire:click="closeSessionModal">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save Session</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
