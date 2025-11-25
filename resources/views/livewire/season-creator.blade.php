<div>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Create Season</h1>
        </div>

        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-3">
                <flux:card>
                    <flux:heading size="lg">Season Configuration</flux:heading>

                    <div class="mt-6 space-y-4">
                        <flux:input wire:model.live="numberOfBlocks" label="Number of Blocks" type="number" min="1"
                            max="12" />

                        <flux:input wire:model.live="weeksPerBlock" label="Weeks per Block" type="number"
                            min="1" max="12" />

                        <flux:input wire:model.live="sessionsPerWeek" label="Sessions per Week" type="number"
                            min="1" max="4" />

                        <flux:input wire:model.live="weightPerExercise" label="Weight per Exercise" type="number"
                            min="0" />

                        <flux:input wire:model.live="defaultSets" label="Default Sets" placeholder="14-14-12-12" />
                    </div>
                </flux:card>
            </div>

            <div class="col-span-9">
                <flux:card class="h-full">
                    <div class="flex gap-2 mb-4">
                        @foreach ($this->categories as $category)
                            <flux:button wire:click="setActiveCategory({{ $category->id }})"
                                :variant="$activeCategory === $category->id ? 'primary' : 'ghost'" size="sm">
                                {{ $category->name }}
                            </flux:button>
                        @endforeach
                    </div>

                    @if ($activeCategory)
                        <div class="flex gap-2 mb-6">
                            @for ($block = 1; $block <= ($numberOfBlocks ?? 0); $block++)
                                <flux:button wire:click="setActiveBlock({{ $block }})"
                                    :variant="$activeBlock === $block ? 'filled' : 'subtle'" size="sm">
                                    Block {{ $block }}
                                </flux:button>
                            @endfor
                        </div>

                        <div class="space-y-8">
                            @forelse ($this->selectedExercises as $exercise)
                                <div
                                    wire:key="exercise-{{ $exercise->id }}-block-{{ $activeBlock }}-{{ $weeksPerBlock ?? 0 }}-{{ $sessionsPerWeek ?? 0 }}-{{ $defaultSets }}-{{ $weightPerExercise ?? 0 }}">
                                    <div class="flex items-center justify-between mb-2">
                                        <flux:heading size="lg">{{ $exercise->name }}</flux:heading>
                                        <flux:button wire:click="removeExercise({{ $exercise->id }})" variant="ghost"
                                            size="sm" icon="x-mark" />
                                    </div>

                                    <div class="flex gap-8">
                                        <x-debug-data-table title="Initial Data (Block {{ $activeBlock }})"
                                            :rows="$this->getInitialData($exercise->id)" :set-count="$this->setCount" row-color="bg-green-50" />

                                        <x-debug-data-table title="Current Data (Block {{ $activeBlock }})"
                                            :rows="$this->getCurrentDataRows($exercise->id)" :set-count="$this->setCount" row-color="bg-blue-50" />
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8 text-zinc-500">
                                    No exercises added yet
                                </div>
                            @endforelse
                        </div>

                        <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                            <flux:select wire:change="addExercise($event.target.value)">
                                <flux:select.option value="">Add Exercise...</flux:select.option>
                                @foreach ($this->exercises as $exercise)
                                    @if (!in_array($exercise->id, $categoryExercises[$activeCategory] ?? []))
                                        <flux:select.option value="{{ $exercise->id }}">
                                            {{ $exercise->name }}
                                        </flux:select.option>
                                    @endif
                                @endforeach
                            </flux:select>
                        </div>
                    @endif
                </flux:card>
            </div>
        </div>
    </div>
</div>
