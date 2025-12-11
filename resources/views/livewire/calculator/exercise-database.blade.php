<div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-center justify-between">
        <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Simplified Exercise Database</h3>
        <x-help-tooltip content="Contains exercise definitions with their modifiers. Click on modifier values to edit them inline." />
    </div>
    <div class="p-4 overflow-x-auto">
        <table class="w-full border-collapse border border-zinc-300 dark:border-zinc-600">
            <thead>
                <tr class="bg-zinc-100 dark:bg-zinc-800">
                    <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">ID</th>
                    <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Name</th>
                    <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Modifier</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($exercises as $ex)
                    <tr wire:key="exercise-{{ $ex->id }}">
                        <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">
                            {{ $ex->id }}
                        </td>
                        <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">
                            {{ $ex->name }}
                        </td>
                        <td class="border border-zinc-300 dark:border-zinc-600 w-20 h-10 p-0"
                            x-data="editable_cell($wire, 'updateExerciseModifier', [{{ $ex->id }}], {{ $ex->modifier }}, '%')" @click="startEditing">
                            <div x-show="!editing" class="px-3 py-2 cursor-pointer text-center"
                                x-text="value + '%'"></div>
                            <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                @blur="save" @keydown="handleKeydown" type="number"
                                step="0.1" min="1"
                                class="w-full h-full text-center border border-black outline-none focus:border-black focus:ring-0" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
