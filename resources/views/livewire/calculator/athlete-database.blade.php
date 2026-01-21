<div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-center justify-between">
        <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Simplified Athlete Database</h3>
        <x-help-tooltip content="Contains athlete test data including reps, weight, and calculated 1RM. Click on name, reps, or weight values to edit them inline." />
    </div>

    @if ($showAddForm)
        <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
            <form wire:submit="addAthlete" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <flux:input wire:model="newName" label="Name" placeholder="Athlete name" required />
                <flux:input wire:model="newReps" label="Test Reps" type="number" step="1" min="1" placeholder="1" required />
                <flux:input wire:model="newWeight" label="Test Weight (kg)" type="number" step="0.5" min="1" placeholder="50.0" required />
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
                    <th class="border border-zinc-300 px-3 py-2 w-20 dark:border-zinc-600">Test (Reps)</th>
                    <th class="border border-zinc-300 px-3 py-2 w-28 dark:border-zinc-600">Test (Weight)</th>
                    <th class="border border-zinc-300 px-3 py-2 w-28 dark:border-zinc-600">Test (1RM)</th>
                    <th class="border border-zinc-300 px-3 py-2 w-16 dark:border-zinc-600">Actions</th>
                </tr>
            </thead>
            <tbody wire:key="athlete-tbody-{{ $this->getListKey() }}">
                @foreach ($athletes as $ath)
                    <tr wire:key="athlete-{{ $ath->id }}">
                        <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">
                            {{ $ath->id }}
                        </td>
                        <td class="border border-zinc-300 dark:border-zinc-600 p-0"
                            x-data="editable_cell($wire, 'updateAthleteName', [{{ $ath->id }}], {{ json_encode($ath->name) }}, '', false)" @click="startEditing">
                            <div x-show="!editing" class="px-3 py-2 cursor-pointer"
                                x-text="value"></div>
                            <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                @blur="save" @keydown="handleKeydown" type="text"
                                class="w-full px-3 py-2 border border-black outline-none focus:border-black focus:ring-0" />
                        </td>
                        @foreach ($ath->tests as $testIndex => $test)
                            <td class="border border-zinc-300 dark:border-zinc-600 w-20 p-0"
                                x-data="editable_cell($wire, 'updateAthleteTestReps', [{{ $ath->id }}, {{ $testIndex }}], {{ $test->reps }})" @click="startEditing">
                                <div x-show="!editing" class="px-3 py-2 cursor-pointer text-center"
                                    x-text="value"></div>
                                <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                    @blur="save" @keydown="handleKeydown" type="number"
                                    step="1" min="1"
                                    class="w-full px-3 py-2 text-center border border-black outline-none focus:border-black focus:ring-0" />
                            </td>
                            <td class="border border-zinc-300 dark:border-zinc-600 w-28 p-0"
                                x-data="editable_cell($wire, 'updateAthleteTestWeight', [{{ $ath->id }}, {{ $testIndex }}], {{ $test->weight }}, ' kg')" @click="startEditing">
                                <div x-show="!editing" class="px-3 py-2 cursor-pointer text-center"
                                    x-text="value + ' kg'"></div>
                                <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                    @blur="save" @keydown="handleKeydown" type="number"
                                    step="0.5" min="1"
                                    class="w-full px-3 py-2 text-center border border-black outline-none focus:border-black focus:ring-0" />
                            </td>
                            <td class="border border-zinc-300 px-3 py-2 text-center dark:border-zinc-600">
                                {{ number_format($test->oneRepMax, 1) }} kg
                            </td>
                        @endforeach
                        <td class="border border-zinc-300 px-3 py-2 text-center dark:border-zinc-600">
                            <flux:icon.trash-2 class="w-4 h-4 mx-auto cursor-pointer text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" wire:click="removeAthlete({{ $ath->id }})" wire:confirm="Are you sure you want to remove this athlete?" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
