<div class="flex gap-6">
    <x-section title="Athletes" class="w-64 shrink-0 sticky top-4 self-start">
        <div class="flex flex-col gap-1">
            <flux:button wire:click="selectUser(0)" variant="{{ $user === 0 ? 'primary' : 'ghost' }}"
                class="justify-start">
                <span class="flex-1 text-left">Default Athlete</span>
            </flux:button>

            <div class="mx-3">
                <flux:separator class="my-2" variant="subtle" />
            </div>

            @foreach ($this->users as $userItem)
                @php
                    $hasMeasuredData = $this->userHasMeasuredData($userItem->id);
                    $overrideCount = $this->countUserOverrides($userItem->id);
                    $isSelected = $user === $userItem->id;
                @endphp
                <flux:button wire:click="selectUser({{ $userItem->id }})"
                    variant="{{ $isSelected ? 'primary' : 'ghost' }}" class="justify-start">
                    <span class="flex-1 text-left">{{ $userItem->name }}</span>
                    @if (!$hasMeasuredData)
                        <flux:badge size="sm" color="red" class="{{ $isSelected ? '!bg-red-500 !text-white dark:!text-white' : '' }}">!</flux:badge>
                    @endif
                    @if ($overrideCount > 0)
                        <flux:badge size="sm" class="{{ $isSelected ? 'bg-white/20 !text-white dark:!text-black' : '' }}">{{ $overrideCount }}</flux:badge>
                    @endif
                </flux:button>
            @endforeach
        </div>
    </x-section>

    <div class="flex-1 space-y-6">
        <x-section title="Plan">
            @if ($user === 0)
                <flux:heading size="lg">Default Athlete</flux:heading>

                <div class="mt-6 space-y-4 max-w-lg">
                    <div class="flex items-end gap-3">
                        <flux:field class="flex-1">
                            <flux:label>Measured Reps</flux:label>
                            <flux:input.group>
                                <flux:input wire:model.live="measured_reps" type="number" min="1" max="15" step="1" />
                                <flux:input.group.suffix>rep(s)</flux:input.group.suffix>
                            </flux:input.group>
                        </flux:field>

                        <flux:field class="flex-1">
                            <flux:label>Measured Weight</flux:label>
                            <flux:input.group>
                                <flux:input wire:model.live="measured_weight" type="number" min="0" step="0.5" />
                                <flux:input.group.suffix>kg</flux:input.group.suffix>
                            </flux:input.group>
                        </flux:field>

                        <flux:field class="flex-1">
                            <flux:label>Starting 1RM</flux:label>
                            <div class="h-10 flex items-center justify-center rounded-lg bg-amber-500/15 text-amber-400 font-medium">
                                {{ $this->estimatedOneRepMax ? $this->estimatedOneRepMax . 'kg' : 'N/A' }}
                            </div>
                        </flux:field>
                    </div>

                    <div class="flex items-end gap-3">
                        <flux:field class="flex-[2]">
                            <flux:label>Target Goal</flux:label>
                            <flux:input.group>
                                <flux:input wire:model.live="target_goal" type="number" min="0" max="999" step="1" />
                                <flux:input.group.suffix>%</flux:input.group.suffix>
                            </flux:input.group>
                        </flux:field>

                        <flux:field class="flex-1">
                            <flux:label>Target 1RM</flux:label>
                            <div class="h-10 flex items-center justify-center rounded-lg bg-green-500/15 text-green-400 font-medium">
                                {{ $this->targetOneRepMax ? $this->targetOneRepMax . 'kg' : 'N/A' }}
                            </div>
                        </flux:field>
                    </div>
                </div>
            @elseif ($this->selectedUser)
                <flux:heading size="lg">{{ $this->selectedUser->name }}</flux:heading>

                <div class="mt-6 space-y-4 max-w-lg">
                    <div class="flex items-end gap-3">
                        <flux:field class="flex-1">
                            <flux:label>Measured Reps</flux:label>
                            <flux:input.group>
                                <flux:input wire:model.live="measured_reps" type="number" min="1" max="15" step="1" />
                                <flux:input.group.suffix>rep(s)</flux:input.group.suffix>
                            </flux:input.group>
                        </flux:field>

                        <flux:field class="flex-1">
                            <flux:label>Measured Weight</flux:label>
                            <flux:input.group>
                                <flux:input wire:model.live="measured_weight" type="number" min="0" step="0.5" />
                                <flux:input.group.suffix>kg</flux:input.group.suffix>
                            </flux:input.group>
                        </flux:field>

                        <flux:field class="flex-1">
                            <flux:label>Starting 1RM</flux:label>
                            <div class="h-10 flex items-center justify-center rounded-lg bg-amber-500/15 text-amber-400 font-medium">
                                {{ $this->estimatedOneRepMax ? $this->estimatedOneRepMax . 'kg' : 'N/A' }}
                            </div>
                        </flux:field>
                    </div>

                    <div class="flex items-end gap-3">
                        <flux:field class="flex-[2]">
                            <flux:label>Target Goal</flux:label>
                            <flux:input.group>
                                <flux:input wire:model.live="target_goal" type="number" min="0" max="999" step="1"
                                    placeholder="{{ $this->getPlaceholder('target_goal') }}" />
                                <flux:input.group.suffix>%</flux:input.group.suffix>
                            </flux:input.group>
                        </flux:field>

                        <flux:field class="flex-1">
                            <flux:label>Target 1RM</flux:label>
                            <div class="h-10 flex items-center justify-center rounded-lg bg-green-500/15 text-green-400 font-medium">
                                {{ $this->targetOneRepMax ? $this->targetOneRepMax . 'kg' : 'N/A' }}
                            </div>
                        </flux:field>
                    </div>
                </div>
            @else
                <flux:text class="text-zinc-500">Select an athlete to view their plan.</flux:text>
            @endif
        </x-section>

        @if ($user !== null && count($this->programs) > 0)
            @foreach ($this->programs as $program)
                <x-section :title="$program->name">
                    @if ($program->exercises->count() > 0)
                        <div class="flex flex-wrap gap-6">
                            @foreach ($program->exercises as $exercise)
                                @php
                                    $extra = $this->getPivotExtra($program->id, $exercise->id);
                                    $pivotExtra = [
                                        'oneRepMaxModifier' => $extra['oneRepMaxModifier'] ?? 100,
                                        'startingReps' => $extra['startingReps'] ?? null,
                                        'sets' => $extra['sets'] ?? null,
                                    ];
                                    $config = $this->getExerciseConfig($exercise->id, $pivotExtra);
                                    $block = $this->generateBlock($exercise->id, $pivotExtra);
                                    if ($block) {
                                        $block = $this->applyCellOverrides($block, $exercise->id);
                                    }
                                    $cellOverrides = $this->getCellOverrides($exercise->id);
                                    $userSpecificCellOverrides = $this->getUserSpecificCellOverrides($exercise->id);
                                @endphp
                                <x-training.exercise-block
                                    :block="$block"
                                    :exercise="$exercise"
                                    :exerciseId="$exercise->id"
                                    :config="$config"
                                    :cellOverrides="$cellOverrides"
                                    :userSpecificCellOverrides="$userSpecificCellOverrides"
                                />
                            @endforeach
                        </div>
                    @else
                        <flux:text class="text-zinc-500">No exercises in this program.</flux:text>
                    @endif
                </x-section>
            @endforeach
        @endif
    </div>
</div>
