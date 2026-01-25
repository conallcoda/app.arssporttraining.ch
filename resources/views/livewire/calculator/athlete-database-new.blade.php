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
                <flux:input wire:model="newName" label="Name" placeholder="Athlete name" required />
                <flux:input wire:model="newReps" label="Test Reps" type="number" step="1" min="1" placeholder="1" required />
                <flux:input wire:model="newWeight" label="Test Weight (kg)" type="number" step="0.5" min="1" placeholder="50.0" required />
                <flux:input wire:model="newTargetModifier" label="Modifier (Target Goal) (%)" type="number" step="0.1" min="0" placeholder="100.0" required />

                <div class="flex gap-2 pt-4">
                    <flux:button type="submit" variant="primary" class="flex-1">Add Athlete</flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column align="center">Test (Reps)</flux:table.column>
            <flux:table.column align="center">Test (Weight)</flux:table.column>
            <flux:table.column align="center">Test (1RM)</flux:table.column>
            <flux:table.column align="center">Modifier (Target Goal)</flux:table.column>
            <flux:table.column align="center">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($athletes as $ath)
                <flux:table.row wire:key="athlete-{{ $ath->id }}">
                    <flux:table.cell class="w-full">
                        <div x-data="editable_cell($wire, 'updateAthleteName', [{{ $ath->id }}], {{ json_encode($ath->name) }}, '', false)" @click="startEditing" class="cursor-pointer w-full">
                            <div x-show="!editing" x-text="value" class="px-2 py-1"></div>
                            <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                @click.stop @blur="save" @keydown="handleKeydown" type="text"
                                class="w-full px-2 py-1 text-sm border border-zinc-300 dark:border-zinc-600 rounded outline-none focus:border-zinc-500 focus:ring-0" />
                        </div>
                    </flux:table.cell>

                    @foreach ($ath->tests as $testIndex => $test)
                        <flux:table.cell align="center">
                            <div x-data="editable_cell($wire, 'updateAthleteTestReps', [{{ $ath->id }}, {{ $testIndex }}], {{ $test->reps }})" @click="startEditing" class="cursor-pointer w-20">
                                <div x-show="!editing" x-text="value" class="px-2 py-1"></div>
                                <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                    @click.stop @blur="save" @keydown="handleKeydown" type="number"
                                    step="1" min="1"
                                    class="w-full px-2 py-1 text-sm text-center border border-zinc-300 dark:border-zinc-600 rounded outline-none focus:border-zinc-500 focus:ring-0" />
                            </div>
                        </flux:table.cell>

                        <flux:table.cell align="center">
                            <div x-data="editable_cell($wire, 'updateAthleteTestWeight', [{{ $ath->id }}, {{ $testIndex }}], {{ $test->weight }}, ' kg')" @click="startEditing" class="cursor-pointer w-24">
                                <div x-show="!editing" x-text="value + ' kg'" class="px-2 py-1"></div>
                                <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                    @click.stop @blur="save" @keydown="handleKeydown" type="number"
                                    step="0.5" min="1"
                                    class="w-full px-2 py-1 text-sm text-center border border-zinc-300 dark:border-zinc-600 rounded outline-none focus:border-zinc-500 focus:ring-0" />
                            </div>
                        </flux:table.cell>

                        <flux:table.cell align="center">
                            {{ number_format($test->oneRepMax, 1) }} kg
                        </flux:table.cell>
                    @endforeach

                    <flux:table.cell align="center">
                        <div x-data="editable_cell($wire, 'updateAthleteTargetModifier', [{{ $ath->id }}], {{ $ath->target_modifier }}, '%')" @click="startEditing" class="cursor-pointer w-24">
                            <div x-show="!editing" x-text="value + '%'" class="px-2 py-1"></div>
                            <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                @click.stop @blur="save" @keydown="handleKeydown" type="number"
                                step="0.1" min="0"
                                class="w-full px-2 py-1 text-sm text-center border border-zinc-300 dark:border-zinc-600 rounded outline-none focus:border-zinc-500 focus:ring-0" />
                        </div>
                    </flux:table.cell>

                    <flux:table.cell align="center">
                        <div class="flex items-center justify-center gap-2">
                            <flux:button variant="ghost" size="xs" icon="arrow-up"
                                wire:click="moveAthleteUp({{ $ath->id }})"
                                :disabled="$loop->first" />
                            <flux:button variant="ghost" size="xs" icon="arrow-down"
                                wire:click="moveAthleteDown({{ $ath->id }})"
                                :disabled="$loop->last" />
                            <flux:button variant="ghost" size="xs" icon="trash-2"
                                wire:click="removeAthlete({{ $ath->id }})"
                                wire:confirm="Are you sure you want to remove this athlete?"
                                class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
