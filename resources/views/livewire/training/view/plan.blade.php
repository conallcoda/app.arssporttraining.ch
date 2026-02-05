<div class="flex gap-6">
    <x-section title="Plans" class="w-64 shrink-0 sticky top-4 self-start">
        <div class="flex flex-col gap-1">
            <flux:button wire:click="selectUser(null)" variant="{{ $user === null ? 'primary' : 'ghost' }}"
                class="justify-start">
                <span class="flex-1 text-left">Default</span>
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
                <flux:button wire:key="user-btn-{{ $userItem->id }}" wire:click="selectUser({{ $userItem->id }})"
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
        @if ($user === null)
            <flux:heading size="xl">Default</flux:heading>
        @elseif ($this->selectedUser)
            <flux:heading size="xl">{{ $this->selectedUser->name }}</flux:heading>
        @endif

        <div class="flex gap-6">
            <x-section title="Target" class="flex-1">
                @if ($user === null)
                    <div class="space-y-4">
                        <div class="flex items-end gap-3">
                            <flux:field class="flex-1">
                                <flux:label>Measured Reps</flux:label>
                                <flux:input.group class="{{ empty($measured_reps) ? '[&>*]:!bg-red-500/20' : '' }}">
                                    <flux:input wire:model.live.debounce.500ms="measured_reps" type="number" min="1" max="15" step="1" />
                                    <flux:input.group.suffix>rep(s)</flux:input.group.suffix>
                                </flux:input.group>
                            </flux:field>

                            <flux:field class="flex-1">
                                <flux:label>Measured Weight</flux:label>
                                <flux:input.group class="{{ empty($measured_weight) ? '[&>*]:!bg-red-500/20' : '' }}">
                                    <flux:input wire:model.live.debounce.500ms="measured_weight" type="number" min="0" step="0.5" />
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
                                    <flux:input wire:model.live.debounce.500ms="target_goal" type="number" min="0" max="999" step="1" />
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
                    <div class="space-y-4">
                        <div class="flex items-end gap-3">
                            <flux:field class="flex-1">
                                <flux:label>Measured Reps</flux:label>
                                <flux:input.group class="{{ empty($measured_reps) ? '[&>*]:!bg-red-500/20' : '' }}">
                                    <flux:input wire:model.live.debounce.500ms="measured_reps" type="number" min="1" max="15" step="1" />
                                    <flux:input.group.suffix>rep(s)</flux:input.group.suffix>
                                </flux:input.group>
                            </flux:field>

                            <flux:field class="flex-1">
                                <flux:label>Measured Weight</flux:label>
                                <flux:input.group class="{{ empty($measured_weight) ? '[&>*]:!bg-red-500/20' : '' }}">
                                    <flux:input wire:model.live.debounce.500ms="measured_weight" type="number" min="0" step="0.5" />
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
                                    <flux:input wire:model.live.debounce.500ms="target_goal" type="number" min="0" max="999" step="1"
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

            <x-section title="Schedule" class="flex-1">
                @if ($user === null)
                    <div class="space-y-4">
                        <div class="flex items-end gap-3">
                            <flux:field class="flex-1">
                                <flux:label>Start Date</flux:label>
                                <flux:select wire:model.live="start_date">
                                    @foreach ($this->weekOptions as $value => $label)
                                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>

                            <flux:field class="flex-1">
                                <flux:label>Duration</flux:label>
                                <flux:input.group>
                                    <flux:input wire:model.live.debounce.500ms="duration" type="number" min="1" max="10" step="1" />
                                    <flux:input.group.suffix>weeks</flux:input.group.suffix>
                                </flux:input.group>
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Programs</flux:label>
                            <flux:pillbox wire:model.live="programs_selected" multiple>
                                @foreach ($this->programOptions as $id => $name)
                                    <flux:pillbox.option value="{{ $id }}">{{ $name }}</flux:pillbox.option>
                                @endforeach
                            </flux:pillbox>
                        </flux:field>
                    </div>
                @elseif ($this->selectedUser)
                    <div class="space-y-4">
                        <div class="flex items-end gap-3">
                            <flux:field class="flex-1">
                                <flux:label>Start Date</flux:label>
                                <flux:select wire:model.live="start_date">
                                    @foreach ($this->weekOptions as $value => $label)
                                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>

                            <flux:field class="flex-1">
                                <flux:label>Duration</flux:label>
                                <flux:input.group>
                                    <flux:input wire:model.live.debounce.500ms="duration" type="number" min="1" max="10" step="1"
                                        placeholder="{{ $this->getPlaceholder('duration') }}" />
                                    <flux:input.group.suffix>weeks</flux:input.group.suffix>
                                </flux:input.group>
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Programs</flux:label>
                            <flux:pillbox wire:model.live="programs_selected" multiple>
                                @foreach ($this->programOptions as $id => $name)
                                    <flux:pillbox.option value="{{ $id }}">{{ $name }}</flux:pillbox.option>
                                @endforeach
                            </flux:pillbox>
                        </flux:field>
                    </div>
                @else
                    <flux:text class="text-zinc-500">Select an athlete to view their plan.</flux:text>
                @endif
            </x-section>
        </div>

        @php
            $selectedProgramIds = array_map('intval', $programs_selected);
            $selectedProgramsKey = implode('-', $selectedProgramIds) ?: 'none';
            $hasMeasuredData = !empty($measured_reps) && !empty($measured_weight);
        @endphp

        @if ($hasMeasuredData)
            <div wire:key="programs-container-{{ $user ?? 'default' }}-{{ $selectedProgramsKey }}">
                @foreach ($this->programs as $program)
                    @if (!in_array($program->id, $selectedProgramIds))
                        @continue
                    @endif
                    <x-section wire:key="program-section-{{ $program->id }}" :title="$program->name" class="mb-6">
                        @if ($program->exercises->count() > 0)
                            <div class="flex flex-wrap gap-6">
                                @foreach ($program->exercises as $exercise)
                                    @php
                                        $configData = $this->getPivotConfig($program->id, $exercise->id);
                                        $exerciseTypeConfig = $exercise->config?->strength;
                                        $pivotConfig = [
                                            'oneRepMaxModifier' => $configData['oneRepMaxModifier'] ?? 100,
                                            'startingReps' => $configData['startingReps'] ?? null,
                                            'sets' => $configData['sets'] ?? null,
                                            'tut' => $configData['tut'] ?? $exerciseTypeConfig?->timeUnderTension ?? null,
                                            'rest' => $configData['rest'] ?? $exerciseTypeConfig?->rest ?? null,
                                        ];
                                        $config = $this->getExerciseConfig($exercise->id, $pivotConfig);
                                        $block = $this->generateBlock($exercise->id, $pivotConfig);
                                        if ($block) {
                                            $block = $this->applyCellOverrides($block, $exercise->id);
                                        }
                                        $cellOverrides = $this->getCellOverrides($exercise->id);
                                        $userSpecificCellOverrides = $this->getUserSpecificCellOverrides($exercise->id);
                                        $weekOverrides = $this->getWeekOverrides($exercise->id);
                                        $userSpecificWeekOverrides = $this->getUserSpecificWeekOverrides($exercise->id);
                                    @endphp
                                    <x-training.exercise-block
                                        wire:key="exercise-block-{{ $exercise->id }}-{{ $user ?? 'default' }}"
                                        :block="$block"
                                        :exercise="$exercise"
                                        :exerciseId="$exercise->id"
                                        :config="$config"
                                        :cellOverrides="$cellOverrides"
                                        :userSpecificCellOverrides="$userSpecificCellOverrides"
                                        :weekOverrides="$weekOverrides"
                                        :userSpecificWeekOverrides="$userSpecificWeekOverrides"
                                        :isDefaultUser="$user === null"
                                        :startDate="$start_date"
                                    />
                                @endforeach
                            </div>
                        @else
                            <flux:text class="text-zinc-500">No exercises in this program.</flux:text>
                        @endif
                    </x-section>
                @endforeach
            </div>
        @else
            <flux:callout color="red" icon="triangle-alert">
                <flux:callout.heading>Missing measured data</flux:callout.heading>
                <flux:callout.text>Enter measured reps and measured weight above to generate the training plan.</flux:callout.text>
            </flux:callout>
        @endif
    </div>
</div>
