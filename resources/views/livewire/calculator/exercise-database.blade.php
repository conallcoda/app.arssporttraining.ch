<div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-center justify-between">
        <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Simplified Exercise Database</h3>
        <x-help-tooltip content="Contains exercise definitions with their modifiers. Click on name or modifier values to edit them inline." />
    </div>

    @if ($showAddForm)
        <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
            <form wire:submit="addExercise" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <flux:input wire:model="newName" label="Name" placeholder="Exercise name" required />
                <flux:input wire:model="newModifier" label="Modifier (%)" type="number" step="0.1" min="1" placeholder="100.0" required />
                <div class="flex items-end gap-2">
                    <flux:button type="submit" variant="primary" class="flex-1">Add</flux:button>
                    <flux:button type="button" wire:click="cancelAdd">Cancel</flux:button>
                </div>
            </form>
        </div>
    @endif

    <div class="p-4 overflow-x-auto">
        <div class="flex justify-end mb-3">
            <flux:button variant="ghost" size="sm" wire:click="toggleAddForm" icon="plus">
                Add
            </flux:button>
        </div>
        <table class="w-full border-collapse border border-zinc-300 dark:border-zinc-600">
            <thead>
                <tr class="bg-zinc-100 dark:bg-zinc-800">
                    <th class="border border-zinc-300 px-3 py-2 w-16 dark:border-zinc-600">ID</th>
                    <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Name</th>
                    <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Modifier</th>
                    <th class="border border-zinc-300 px-3 py-2 w-16 dark:border-zinc-600">Actions</th>
                </tr>
            </thead>
            <tbody wire:key="exercise-tbody-{{ $this->getListKey() }}">
                @foreach ($exercises as $ex)
                    <tr wire:key="exercise-{{ $ex->id }}">
                        <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">
                            {{ $ex->id }}
                        </td>
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
                            <flux:icon.trash-2 class="w-4 h-4 mx-auto cursor-pointer text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" wire:click="removeExercise({{ $ex->id }})" wire:confirm="Are you sure you want to remove this exercise?" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
