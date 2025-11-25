<div class="space-y-4" x-data="{
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
                $wire.dispatch('action', {
                    type: 'session',
                    action: 'swap',
                    params: {
                        session1Id: this.draggedSessionUuid,
                        session2Id: targetSessionUuid
                    }
                });
            } else {
                $wire.dispatch('action', {
                    type: 'session',
                    action: 'move',
                    params: {
                        sessionId: this.draggedSessionUuid,
                        newDay: targetDay,
                        newSlot: targetSlot
                    }
                });
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
        logger()->info('Building session grid', [
            'weekUuid' => $week->uuid,
            'childrenCount' => count($week->children),
            'children' => array_map(fn($s) => [
                'uuid' => $s->uuid,
                'day' => $s->data->day ?? 'null',
                'slot' => $s->data->slot ?? 'null',
                'type' => $s->type
            ], $week->children)
        ]);

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
                        <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-2 text-center text-sm font-semibold"
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
                                $sessionCategory =
                                    $session && $session->data->category
                                        ? $categoriesById[$session->data->category] ?? null
                                        : null;
                                $cellKey = $dayIndex . '-' . $sequenceIndex;
                            @endphp
                            <td class="border border-zinc-200 dark:border-zinc-700 p-2 h-24 align-top transition-all duration-200"
                                :class="isDraggingOver === '{{ $cellKey }}' ? 'bg-blue-100 dark:bg-blue-900/30 scale-105' :
                                    'hover:bg-zinc-50 dark:hover:bg-zinc-800/50'"
                                @dragover.prevent="dragOver({{ $dayIndex }}, {{ $sequenceIndex }})"
                                @dragleave="dragLeave()"
                                @drop.prevent="drop('{{ $session?->uuid }}', {{ $dayIndex }}, {{ $sequenceIndex }})">

                                @if ($sessionCategory)
                                    <div class="h-full flex items-center justify-center rounded px-2 py-1 cursor-move group relative transition-transform duration-200 {{ $selectedSessionUuid === $session->uuid ? 'ring-2 ring-blue-500' : '' }}"
                                        :class="draggedSessionUuid === '{{ $session->uuid }}' ? 'opacity-50 scale-95' : ''"
                                        style="background-color: {{ $this->getColorValue($sessionCategory->background_color) }}; color: {{ $this->getColorValue($sessionCategory->text_color) }};"
                                        draggable="true"
                                        @dragstart="startDrag('{{ $session->uuid }}', {{ $dayIndex }}, {{ $sequenceIndex }})"
                                        @dragend="draggedSessionUuid = null; isDraggingOver = null"
                                        @click="$wire.selectSession('{{ $session->uuid }}')"
                                        @dblclick="$wire.openSessionModal('{{ $session->uuid }}', {{ $dayIndex }}, {{ $sequenceIndex }})">
                                        <span class="text-sm font-medium">{{ $sessionCategory->name }}</span>
                                        <div
                                            class="absolute inset-0 bg-black/0 group-hover:bg-black/10 rounded transition-colors pointer-events-none">
                                        </div>
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

    @if ($selectedSession)
        @php
            $selectedCategory = $selectedSession->data->category
                ? $categoriesById[$selectedSession->data->category] ?? null
                : null;
            $exercisesById = \App\Models\Exercise\Exercise::all()->keyBy('id');
        @endphp
        <div class="mt-6 border border-zinc-200 dark:border-zinc-700 rounded-lg">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    @if ($selectedCategory)
                        <div class="w-4 h-4 rounded"
                            style="background-color: {{ $this->getColorValue($selectedCategory->background_color) }};">
                        </div>
                    @endif
                    <flux:heading size="sm">{{ $selectedCategory?->name ?? 'Session' }} - {{ $days[$selectedSession->data->day] }} {{ $timePeriods[$selectedSession->data->slot] }}</flux:heading>
                </div>
                <flux:button size="sm" variant="ghost" wire:click="selectSession(null)">
                    <x-lucide-x class="w-4 h-4" />
                </flux:button>
            </div>

            <div class="p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="xs">Exercises</flux:heading>
                    <div x-data="{ showAddExercise: false, newExerciseId: null }">
                        <flux:button x-show="!showAddExercise" @click="showAddExercise = true" size="sm" variant="ghost" class="inline-flex items-center gap-1">
                            <x-lucide-plus class="w-4 h-4" />
                            <span>Add Exercise</span>
                        </flux:button>
                        <div x-show="showAddExercise" class="flex items-center gap-2">
                            <select x-model="newExerciseId" class="text-sm border-zinc-300 rounded-md">
                                <option value="">Select exercise...</option>
                                @foreach ($exercisesById as $exercise)
                                    <option value="{{ $exercise->id }}">{{ $exercise->name }}</option>
                                @endforeach
                            </select>
                            <flux:button size="sm" variant="primary"
                                @click="if(newExerciseId) { $wire.addExerciseToSession('{{ $selectedSession->uuid }}', parseInt(newExerciseId)); showAddExercise = false; newExerciseId = null; }">
                                Add
                            </flux:button>
                            <flux:button size="sm" variant="ghost" @click="showAddExercise = false; newExerciseId = null">
                                Cancel
                            </flux:button>
                        </div>
                    </div>
                </div>

                @if (count($selectedSession->children) > 0)
                    <div class="space-y-3">
                        @foreach ($selectedSession->children as $exerciseIndex => $exerciseNode)
                            @php
                                $exerciseModel = $exercisesById[$exerciseNode->data->exercise] ?? null;
                            @endphp
                            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg" wire:key="exercise-node-{{ $exerciseNode->uuid }}">
                                <div class="p-3 bg-zinc-50 dark:bg-zinc-800 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-semibold text-zinc-500 bg-zinc-200 dark:bg-zinc-700 px-2 py-0.5 rounded">
                                            {{ $exerciseIndex + 1 }}
                                        </span>
                                        <span class="font-medium text-sm">
                                            {{ $exerciseModel?->name ?? 'Unknown Exercise' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <flux:button size="xs" variant="ghost"
                                            wire:click="deleteExerciseNode('{{ $exerciseNode->uuid }}')"
                                            wire:confirm="Are you sure you want to delete this exercise and all its sets?">
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </flux:button>
                                    </div>
                                </div>

                                <div class="p-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-medium text-zinc-500">Sets</span>
                                        <flux:button size="xs" variant="ghost" class="inline-flex items-center gap-1"
                                            wire:click="addSetToExercise('{{ $exerciseNode->uuid }}')">
                                            <x-lucide-plus class="w-3 h-3" />
                                            <span>Add Set</span>
                                        </flux:button>
                                    </div>

                                    @if (count($exerciseNode->children) > 0)
                                        <div class="space-y-2">
                                            @foreach ($exerciseNode->children as $setIndex => $setNode)
                                                <div class="flex items-center gap-3 p-2 bg-zinc-50 dark:bg-zinc-800 rounded" wire:key="set-node-{{ $setNode->uuid }}"
                                                    x-data="{ editing: false, reps: {{ $setNode->data->reps }}, weight: {{ $setNode->data->weight }} }">
                                                    <span class="text-xs text-zinc-400 w-6">{{ $setIndex + 1 }}</span>

                                                    <template x-if="!editing">
                                                        <div class="flex items-center gap-4 flex-1" @dblclick="editing = true">
                                                            <span class="text-sm">
                                                                <span class="font-medium">{{ $setNode->data->reps }}</span>
                                                                <span class="text-zinc-500">reps</span>
                                                            </span>
                                                            <span class="text-zinc-300">x</span>
                                                            <span class="text-sm">
                                                                <span class="font-medium">{{ $setNode->data->weight }}</span>
                                                                <span class="text-zinc-500">kg</span>
                                                            </span>
                                                        </div>
                                                    </template>

                                                    <template x-if="editing">
                                                        <div class="flex items-center gap-2 flex-1">
                                                            <input type="number" x-model="reps" class="w-16 text-sm border-zinc-300 rounded px-2 py-1" min="1">
                                                            <span class="text-zinc-500 text-sm">reps x</span>
                                                            <input type="number" x-model="weight" step="0.5" class="w-20 text-sm border-zinc-300 rounded px-2 py-1" min="0">
                                                            <span class="text-zinc-500 text-sm">kg</span>
                                                            <flux:button size="xs" variant="primary"
                                                                @click="$wire.updateSet('{{ $setNode->uuid }}', parseInt(reps), parseFloat(weight)); editing = false">
                                                                Save
                                                            </flux:button>
                                                            <flux:button size="xs" variant="ghost" @click="editing = false; reps = {{ $setNode->data->reps }}; weight = {{ $setNode->data->weight }}">
                                                                Cancel
                                                            </flux:button>
                                                        </div>
                                                    </template>

                                                    <flux:button x-show="!editing" size="xs" variant="ghost"
                                                        wire:click="deleteSet('{{ $setNode->uuid }}')">
                                                        <x-lucide-trash-2 class="w-3 h-3" />
                                                    </flux:button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-xs text-zinc-400">No sets added yet. Click "Add Set" to add one.</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-zinc-500">No exercises in this session. Click "Add Exercise" to add one.</p>
                @endif
            </div>
        </div>
    @endif

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
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="sessionCategory" />
            </flux:field>

            <flux:field>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:label>Exercises</flux:label>
                        <flux:button type="button" size="sm" variant="ghost" class="inline-flex items-center gap-1"
                            wire:click="addExercise">
                            <x-lucide-plus class="w-4 h-4" />
                            <span>Add Exercise</span>
                        </flux:button>
                    </div>

                    @if (count($sessionExercises) > 0)
                        <div class="space-y-2">
                            @foreach ($sessionExercises as $index => $exerciseId)
                                @php
                                    $isFirst = $index === 0;
                                    $isLast = $index === count($sessionExercises) - 1;
                                @endphp
                                <div class="flex items-center gap-2" wire:key="exercise-{{ $index }}">
                                    <div class="flex-1">
                                        <flux:select wire:model="sessionExercises.{{ $index }}"
                                            placeholder="Select an exercise" searchable>
                                            <option value="">Select an exercise</option>
                                            @foreach (\App\Models\Exercise\Exercise::orderBy('name')->get() as $exercise)
                                                <option value="{{ $exercise->id }}">{{ $exercise->name }}</option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                    <div class="flex gap-1">
                                        <button type="button" wire:click="moveExerciseUp({{ $index }})"
                                            {{ $isFirst ? 'disabled' : '' }}
                                            class="inline-flex items-center justify-center gap-2 px-2 py-1.5 text-sm font-medium rounded transition-colors {{ $isFirst ? 'opacity-40 cursor-not-allowed text-zinc-400' : 'text-zinc-700 hover:bg-zinc-100' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 15l7-7 7 7" />
                                            </svg>
                                        </button>
                                        <button type="button" wire:click="moveExerciseDown({{ $index }})"
                                            {{ $isLast ? 'disabled' : '' }}
                                            class="inline-flex items-center justify-center gap-2 px-2 py-1.5 text-sm font-medium rounded transition-colors {{ $isLast ? 'opacity-40 cursor-not-allowed text-zinc-400' : 'text-zinc-700 hover:bg-zinc-100' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <flux:button type="button" size="sm" variant="ghost"
                                            wire:click="removeExercise({{ $index }})">
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </flux:button>
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
                    @if ($editingSessionUuid)
                        <flux:button type="button" variant="danger" class="inline-flex items-center gap-1"
                            wire:click="deleteSession('{{ $editingSessionUuid }}')">
                            <x-lucide-trash-2 class="w-4 h-4" />
                            <span>Delete</span>
                        </flux:button>
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
