<div>
    <div class="mb-4 flex justify-end">
        <flux:button variant="ghost" size="sm" wire:click="toggleAddGroupModal" icon="plus">
            Add Program
        </flux:button>
    </div>

    <flux:modal wire:model="showAddGroupModal" class="min-w-[400px]">
        <form wire:submit="addGroup" class="space-y-6">
            <flux:heading size="lg">Add New Program</flux:heading>

            <flux:input wire:model="newGroupName" placeholder="Kraft 1A" required />

            <div class="flex gap-2 justify-end">
                <flux:button type="button" variant="ghost" wire:click="toggleAddGroupModal">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Add Program</flux:button>
            </div>
        </form>
    </flux:modal>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2" wire:key="groups-grid-{{ $this->getGroupsKey() }}">
        @foreach ($groups as $group)
            <div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden" wire:key="group-{{ $group }}">
                <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                    <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $group }}</h3>
                </div>

                @if ($showAddForm[$group] ?? false)
                    <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                        <form wire:submit="addExercise('{{ $group }}')" class="grid grid-cols-1 gap-3">
                            <flux:input wire:model="newName.{{ $group }}" label="Name" placeholder="Exercise name" required />
                            <flux:input wire:model="newModifier.{{ $group }}" label="Modifier (1RM) (%)" type="number" step="0.1" min="1" placeholder="100.0" required />
                            <div class="flex gap-2">
                                <flux:button type="submit" variant="primary" class="flex-1">Add</flux:button>
                                <flux:button type="button" wire:click="cancelAdd('{{ $group }}')">Cancel</flux:button>
                            </div>
                        </form>
                    </div>
                @endif

                <div class="p-4 overflow-x-auto">
                    <div class="flex justify-end mb-3">
                        <flux:button variant="ghost" size="sm" wire:click="toggleAddForm('{{ $group }}')" icon="plus">
                            Add Exercise
                        </flux:button>
                    </div>
                    @php
                        $groupExercises = $this->getExercisesForGroup($group);
                    @endphp

                    @if (count($groupExercises) > 0)
                        <table class="w-full border-collapse border border-zinc-300 dark:border-zinc-600">
                            <thead>
                                <tr class="bg-zinc-100 dark:bg-zinc-800">
                                    <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Name</th>
                                    <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Modifier (1RM)</th>
                                    <th class="border border-zinc-300 px-3 py-2 w-16 dark:border-zinc-600">Actions</th>
                                </tr>
                            </thead>
                            <tbody wire:key="exercise-tbody-{{ $group }}-{{ $this->getListKey($group) }}">
                                @foreach ($groupExercises as $ex)
                                <tr wire:key="exercise-{{ $ex->id }}">
                                    <td class="border border-zinc-300 dark:border-zinc-600 p-0"
                                        x-data="editable_cell($wire, 'updateExerciseName', [{{ $ex->id }}], {{ json_encode($ex->name) }}, '', false)" @click="startEditing">
                                        <div x-show="!editing" class="px-3 py-2 cursor-pointer"
                                            x-text="value"></div>
                                        <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                            @blur="save" @keydown="handleKeydown" type="text"
                                            class="w-full px-3 py-2 border border-black outline-none focus:border-black focus:ring-0" />
                                    </td>
                                    <td class="border border-zinc-300 dark:border-zinc-600 w-24 p-0"
                                        x-data="editable_cell($wire, 'updateExerciseModifier', [{{ $ex->id }}], {{ $ex->modifier }}, '%')" @click="startEditing">
                                        <div x-show="!editing" class="px-3 py-2 cursor-pointer text-center"
                                            x-text="value + '%'"></div>
                                        <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                            @blur="save" @keydown="handleKeydown" type="number"
                                            step="0.1" min="1"
                                            class="w-full px-3 py-2 text-center border border-black outline-none focus:border-black focus:ring-0" />
                                    </td>
                                    <td class="border border-zinc-300 px-3 py-2 text-center dark:border-zinc-600">
                                        <div class="flex items-center justify-center gap-2">
                                            <flux:icon.arrow-up
                                                class="w-4 h-4 cursor-pointer text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 {{ $loop->first ? 'opacity-30 cursor-not-allowed pointer-events-none' : '' }}"
                                                wire:click="moveExerciseUp({{ $ex->id }}, '{{ $group }}')" />
                                            <flux:icon.arrow-down
                                                class="w-4 h-4 cursor-pointer text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 {{ $loop->last ? 'opacity-30 cursor-not-allowed pointer-events-none' : '' }}"
                                                wire:click="moveExerciseDown({{ $ex->id }}, '{{ $group }}')" />
                                            <flux:icon.trash-2
                                                class="w-4 h-4 cursor-pointer text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400"
                                                wire:click="removeExercise({{ $ex->id }})"
                                                wire:confirm="Are you sure you want to remove this exercise?" />
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center text-zinc-500 dark:text-zinc-400 p-8">
                            No exercises in this group. Click "Add Exercise" to create one.
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
