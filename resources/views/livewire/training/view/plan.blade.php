<div class="flex gap-6 focus:outline-none">
    @if ($users->isNotEmpty())
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
                        $isSelected = $user === $userItem->id;
                        $overrideCount = $this->userOverrideCounts[$userItem->id] ?? 0;
                    @endphp
                    <flux:button wire:key="user-btn-{{ $userItem->id }}" wire:click="selectUser({{ $userItem->id }})"
                        variant="{{ $isSelected ? 'primary' : 'ghost' }}" class="justify-start">
                        <span class="flex-1 text-left">{{ $userItem->name }}</span>
                        @if ($overrideCount > 0 && $isSelected)
                            <flux:badge size="sm" color="lime" class="!text-green-700">{{ $overrideCount }}</flux:badge>
                        @elseif ($overrideCount > 0)
                            <flux:badge size="sm" color="lime">{{ $overrideCount }}</flux:badge>
                        @endif
                    </flux:button>
                @endforeach
            </div>
        </x-section>
    @endif

    <div class="flex-1 space-y-6">
        @if ($user === null && ! $exercisePlan->isTemplate())
            <div class="flex items-center justify-between">
                <flux:heading size="xl">Default</flux:heading>
                <div class="flex gap-2">
                    <flux:button wire:click="confirmResetDefaultSettings" variant="primary" size="sm" icon="rotate-ccw"
                        :disabled="! $this->hasDefaultOverrides">
                        Reset Default Settings
                    </flux:button>
                    @if ($users->isNotEmpty())
                        <flux:button wire:click="confirmResetUserSettings" variant="primary" size="sm" icon="rotate-ccw"
                            :disabled="! $this->hasUserOverrides">
                            Reset User Settings
                        </flux:button>
                    @endif
                </div>
            </div>
        @elseif ($this->selectedUser)
            <div class="flex items-center justify-between">
                <flux:heading size="xl">{{ $this->selectedUser->name }}</flux:heading>
                <flux:button wire:click="confirmResetSelectedUserSettings" variant="primary" size="sm" icon="rotate-ccw"
                    :disabled="! $this->hasSelectedUserOverrides">
                    Reset User Settings
                </flux:button>
            </div>
        @endif

        <div class="flex gap-6">
            <x-section title="Target" class="w-[55%]">
                @if ($user === null || $this->selectedUser)
                    <div class="space-y-4">
                        <div class="flex items-end gap-3">
                            <flux:field class="flex-1">
                                <flux:label>Measured Reps</flux:label>
                                <flux:input.group wire:ignore>
                                    <flux:input wire:model.live.blur="measuredReps" type="number" min="1" step="1" />
                                    <flux:input.group.suffix>rep(s)</flux:input.group.suffix>
                                </flux:input.group>
                            </flux:field>

                            <flux:field class="flex-1">
                                <flux:label>Measured Weight</flux:label>
                                <flux:input.group wire:ignore>
                                    <flux:input wire:model.live.blur="measuredWeight" type="number" min="0" step="0.5" />
                                    <flux:input.group.suffix>kg</flux:input.group.suffix>
                                </flux:input.group>
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Target Goal</flux:label>
                                <flux:input.group wire:ignore>
                                    <flux:input wire:model.live.blur="targetGoal" type="number" min="0" step="1" />
                                    <flux:input.group.suffix>%</flux:input.group.suffix>
                                </flux:input.group>
                            </flux:field>
                        </div>

                        <div class="flex items-end gap-3">
                            <div class="flex-1">
                                <flux:label class="mb-1.5">Starting 1RM</flux:label>
                                @if ($this->starting1RM !== null)
                                    <div class="h-10 flex items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-400 font-medium">
                                        {{ $this->starting1RM }}kg
                                    </div>
                                @else
                                    <div class="h-10 flex items-center justify-center rounded-lg bg-zinc-500/15 text-zinc-400 font-medium">
                                        --
                                    </div>
                                @endif
                            </div>

                            <div class="flex-1">
                                <flux:label class="mb-1.5">Target 1RM</flux:label>
                                @if ($this->target1RM !== null)
                                    <div class="h-10 flex items-center justify-center rounded-lg bg-amber-500/15 text-amber-400 font-medium">
                                        {{ $this->target1RM }}kg
                                    </div>
                                @else
                                    <div class="h-10 flex items-center justify-center rounded-lg bg-zinc-500/15 text-zinc-400 font-medium">
                                        --
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <flux:text class="text-zinc-500">Select an athlete to view their target.</flux:text>
                @endif
            </x-section>

            <x-section title="Schedule" class="w-[45%]">
                @if ($user === null || $this->selectedUser)
                    <div class="space-y-4">
                        <div class="flex items-end gap-3">
                            @if ($users->isNotEmpty())
                                <flux:field class="flex-1">
                                    <flux:label>Start Date</flux:label>
                                    <flux:select wire:model.live="startDate">
                                        @foreach ($this->weekOptions as $value => $label)
                                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </flux:field>
                            @endif

                            <flux:field class="flex-1">
                                <flux:label>Duration</flux:label>
                                <div
                                    class="h-10 flex items-center justify-center rounded-lg bg-blue-500/15 text-blue-400 font-medium cursor-pointer hover:bg-blue-500/25 transition-colors"
                                    x-on:click="Livewire.dispatch('portal:open', {
                                        component: 'training.view.schedule',
                                        props: { exercisePlanId: {{ $exercisePlan->id }}, planType: '{{ addslashes(get_class($exercisePlan)) }}' },
                                        title: 'Schedule',
                                        variant: '',
                                        class: 'min-w-[70rem]'
                                    })"
                                >
                                    {{ $this->weeks }} weeks
                                </div>
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Programs</flux:label>
                            <div class="flex flex-wrap gap-2">
                                @forelse ($this->programs->whereIn('id', $this->programIdsFromSchedule) as $program)
                                    <flux:badge
                                        as="button"
                                        x-on:click="Livewire.dispatch('portal:open', {
                                            component: 'training.view.program-editor',
                                            props: { exercisePlanId: {{ $exercisePlan->id }}, planType: '{{ addslashes(get_class($exercisePlan)) }}', programId: {{ $program->id }} },
                                            title: 'Edit Program',
                                            variant: 'flyout',
                                            class: 'max-w-lg'
                                        })"
                                    >
                                        {{ $program->name }}
                                    </flux:badge>
                                @empty
                                    <flux:text class="text-zinc-500">No programs scheduled</flux:text>
                                @endforelse
                            </div>
                        </flux:field>
                    </div>
                @else
                    <flux:text class="text-zinc-500">Select an athlete to view their plan.</flux:text>
                @endif
            </x-section>
        </div>

        @if (($user === null || $this->selectedUser) && $this->programCategories->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach ($this->programCategories as $category)
                    @if ($selectedCategoryId === $category->id)
                        <flux:badge
                            wire:key="cat-{{ $category->id }}"
                            wire:click="selectCategory({{ $category->id }})"
                            as="button"
                            variant="solid"
                            color="{{ $category->color }}"
                        >
                            {{ $category->name }}
                        </flux:badge>
                    @else
                        <flux:badge
                            wire:key="cat-{{ $category->id }}"
                            wire:click="selectCategory({{ $category->id }})"
                            as="button"
                            color="{{ $category->color }}"
                        >
                            {{ $category->name }}
                        </flux:badge>
                    @endif
                @endforeach
            </div>

            @foreach ($this->programsForCategory as $program)
                @php
                    $config = $exercisePlan->config;
                    [$enabledExercises, $disabledExercises] = $program->exercises->partition(function ($exercise) use ($config, $user) {
                        $planOverrides = $config->defaultExerciseOverrides($exercise->id);
                        if ($user !== null) {
                            $userOverrides = $config->userExerciseOverrides($exercise->id, $user);
                            return ! \App\Data\Training\Config\EffectiveExerciseConfig::resolveDisabled($planOverrides, $userOverrides);
                        }
                        return ! ($planOverrides->disabled ?? false);
                    });
                @endphp
                <div wire:key="program-{{ $program->id }}" class="space-y-4">
                    <flux:heading level="3" size="lg">{{ $program->name }}</flux:heading>

                    @if ($disabledExercises->isNotEmpty())
                        <div class="flex flex-wrap gap-4">
                            @foreach ($disabledExercises as $exercise)
                                <livewire:training.view.plan-exercise-grid
                                    :key="'grid-' . $exercise->id . '-' . ($user ?? 'default') . '-disabled'"
                                    :exercisePlanId="$exercisePlan->id"
                                    :planType="get_class($exercisePlan)"
                                    :exerciseId="$exercise->id"
                                    :userId="$user"
                                    :disabled="true"
                                    :weeks="$this->weeks"
                                    :sessionsPerWeek="$this->sessionsPerWeekByProgram[$program->id] ?? 1"
                                    :planMeasuredReps="$measuredReps"
                                    :planMeasuredWeight="$measuredWeight"
                                    :planTargetGoal="$targetGoal"
                                />
                            @endforeach
                        </div>
                    @endif

                    @if ($enabledExercises->isNotEmpty())
                        <div class="flex flex-wrap gap-4">
                            @foreach ($enabledExercises as $exercise)
                                <livewire:training.view.plan-exercise-grid
                                    :key="'grid-' . $exercise->id . '-' . ($user ?? 'default') . '-enabled'"
                                    :exercisePlanId="$exercisePlan->id"
                                    :planType="get_class($exercisePlan)"
                                    :exerciseId="$exercise->id"
                                    :userId="$user"
                                    :disabled="false"
                                    :weeks="$this->weeks"
                                    :sessionsPerWeek="$this->sessionsPerWeekByProgram[$program->id] ?? 1"
                                    :planMeasuredReps="$measuredReps"
                                    :planMeasuredWeight="$measuredWeight"
                                    :planTargetGoal="$targetGoal"
                                />
                            @endforeach
                        </div>
                    @endif

                    @if ($program->exercises->isEmpty())
                        <flux:text class="text-zinc-500">No exercises in this program.</flux:text>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    <livewire:training.view.plan-exercise-settings-form />

    <flux:modal name="reset-default-settings" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Reset Default Settings?</flux:heading>
                <flux:text class="mt-2">
                    This will remove all default overrides and settings, resetting the default plan to its initial state.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="resetDefaultSettings">
                    Reset Default Settings
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="reset-user-settings" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Reset User Settings?</flux:heading>
                <flux:text class="mt-2">
                    This will remove all user-specific overrides and settings, resetting all users back to the default plan.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="resetUserSettings">
                    Reset User Settings
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="reset-selected-user-settings" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Reset User Settings?</flux:heading>
                <flux:text class="mt-2">
                    This will remove all exercise overrides and settings for {{ $this->selectedUser?->name ?? 'this user' }}, resetting them back to the default plan.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="resetSelectedUserSettings">
                    Reset Settings
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
