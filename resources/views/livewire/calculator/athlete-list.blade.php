<div>
    <div class="flex justify-end mb-3">
        <flux:modal.trigger name="add-athlete">
            <flux:button variant="ghost" size="sm" icon="plus">
                Add
            </flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="add-athlete" flyout class="w-96">
        <div class="space-y-6">
            <flux:heading size="lg">Add Athlete</flux:heading>

            <form wire:submit="addAthlete" class="space-y-4">
                <flux:input wire:model="data.forename" label="Forename" placeholder="Forename" required />
                <flux:input wire:model="data.surname" label="Surname" placeholder="Surname" required />
                <flux:input wire:model="data.reps" label="Test Reps" type="number" step="1" min="1" placeholder="1" required />
                <flux:input wire:model="data.weight" label="Test Weight (kg)" type="number" step="0.5" min="1" placeholder="50.0" required />
                <flux:input wire:model="data.target_modifier" label="Modifier (Target Goal) (%)" type="number" step="0.1" min="0" placeholder="100.0" required />

                <div class="flex gap-2 pt-4">
                    <flux:button type="submit" variant="primary" class="flex-1">Add Athlete</flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:table :paginate="$this->athletes" class="table-fixed">
        <flux:table.columns>
            <flux:table.column class="w-1/6">Forename</flux:table.column>
            <flux:table.column class="w-1/6">Surname</flux:table.column>
            <flux:table.column class="w-24">Test (Reps)</flux:table.column>
            <flux:table.column class="w-28">Test (Weight)</flux:table.column>
            <flux:table.column class="w-28">Test (1RM)</flux:table.column>
            <flux:table.column class="w-36">Modifier (Target Goal)</flux:table.column>
            <flux:table.column class="w-20">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->athletes as $user)
                @php $ath = \App\Models\Training\ExercisePlan\AthleteData::fromUser($user); @endphp
                <flux:table.row wire:key="athlete-{{ $ath->id }}">
                    <flux:table.cell>
                        <div x-data="editable_cell($wire, 'updateAthlete', [{{ $ath->id }}, 'forename'], {{ json_encode($ath->forename) }}, '', false)" @click="startEditing" class="cursor-pointer w-full">
                            <div x-show="!editing" x-text="value" class="px-2 py-1 truncate border border-transparent"></div>
                            <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                @click.stop @blur="save" @keydown="handleKeydown" type="text"
                                class="w-full px-2 py-1 text-sm border border-zinc-300 dark:border-zinc-600 rounded outline-none focus:border-zinc-500 focus:ring-0" />
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div x-data="editable_cell($wire, 'updateAthlete', [{{ $ath->id }}, 'surname'], {{ json_encode($ath->surname) }}, '', false)" @click="startEditing" class="cursor-pointer w-full">
                            <div x-show="!editing" x-text="value" class="px-2 py-1 truncate border border-transparent"></div>
                            <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                @click.stop @blur="save" @keydown="handleKeydown" type="text"
                                class="w-full px-2 py-1 text-sm border border-zinc-300 dark:border-zinc-600 rounded outline-none focus:border-zinc-500 focus:ring-0" />
                        </div>
                    </flux:table.cell>

                    @foreach ($ath->tests as $testIndex => $test)
                        <flux:table.cell>
                            <div x-data="editable_cell($wire, 'updateAthlete', [{{ $ath->id }}, 'test.{{ $testIndex }}.reps'], {{ $test->reps }})" @click="startEditing" class="cursor-pointer w-full">
                                <div x-show="!editing" x-text="value" class="px-2 py-1 border border-transparent"></div>
                                <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                    @click.stop @blur="save" @keydown="handleKeydown" type="number"
                                    step="1" min="1"
                                    class="w-full px-2 py-1 text-sm border border-zinc-300 dark:border-zinc-600 rounded outline-none focus:border-zinc-500 focus:ring-0" />
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div x-data="editable_cell($wire, 'updateAthlete', [{{ $ath->id }}, 'test.{{ $testIndex }}.weight'], {{ $test->weight }}, ' kg')" @click="startEditing" class="cursor-pointer w-full">
                                <div x-show="!editing" x-text="value + ' kg'" class="px-2 py-1 border border-transparent"></div>
                                <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                    @click.stop @blur="save" @keydown="handleKeydown" type="number"
                                    step="0.5" min="1"
                                    class="w-full px-2 py-1 text-sm border border-zinc-300 dark:border-zinc-600 rounded outline-none focus:border-zinc-500 focus:ring-0" />
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="px-2 py-1 border border-transparent">{{ number_format($test->oneRepMax, 1) }} kg</div>
                        </flux:table.cell>
                    @endforeach

                    <flux:table.cell>
                        <div x-data="editable_cell($wire, 'updateAthlete', [{{ $ath->id }}, 'target_modifier'], {{ $ath->target_modifier }}, '%')" @click="startEditing" class="cursor-pointer w-full">
                            <div x-show="!editing" x-text="value + '%'" class="px-2 py-1 border border-transparent"></div>
                            <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                @click.stop @blur="save" @keydown="handleKeydown" type="number"
                                step="0.1" min="0"
                                class="w-full px-2 py-1 text-sm border border-zinc-300 dark:border-zinc-600 rounded outline-none focus:border-zinc-500 focus:ring-0" />
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:button variant="ghost" size="xs" icon="trash-2"
                            wire:click="removeAthlete({{ $ath->id }})"
                            wire:confirm="Are you sure you want to remove this athlete?"
                            class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
