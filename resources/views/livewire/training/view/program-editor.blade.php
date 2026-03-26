<div class="space-y-6">
    <div class="flex flex-col md:flex-row gap-6">
        <x-cms::section :title="__('General')" class="{{ $this->showAthleteContext ? 'md:w-3/4' : 'flex-1' }}">
            @if ($showNameInput)
                <flux:input wire:model="$parent.planProgramName" wire:blur="$parent.savePlanProgramName" :label="__('Name')" size="sm" />
            @endif

            @foreach ($this->fieldsets as $item)
                <x-cms::form.fieldset
                    :fieldset="$item"
                    :prefix="$item->prefix ?? 'data'"
                    :showLegend="false"
                />
            @endforeach

            <div class="grid grid-cols-2 gap-4">
                @foreach ($this->warmProgramFieldsets as $item)
                    <x-cms::form.fieldset
                        :fieldset="$item"
                        :prefix="$item->prefix ?? 'data'"
                        :showLegend="false"
                    />
                @endforeach
            </div>
        </x-cms::section>

        @if ($this->showAthleteContext)
            <x-cms::section :title="$userId === null ? __('Group') : __('Athlete')" class="md:w-1/4 shrink-0">
                <div class="space-y-4">
                    @if ($hasAutoWeightExercises && $planHasBlock && $planBlockGoalLabel)
                        <div>
                            <flux:label class="mb-1.5">{{ __('Block Goal') }}</flux:label>
                            <div>
                                <flux:badge color="zinc" class="text-lg px-3 py-1.5 cursor-pointer" wire:click="$parent.openPlanBlockEdit">
                                    {{ $planBlockGoalLabel }}
                                </flux:badge>
                            </div>
                        </div>
                    @endif

                    @if ($hasAutoWeightExercises && $planHasBlock)
                        <div>
                            @if ($userId === null)
                                <flux:label class="mb-1.5">{{ __('1RM') }}</flux:label>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($planGroupMemberMetrics['oneRepMax'] ?? [] as $member)
                                        <flux:badge
                                            color="{{ $member['label'] ? 'zinc' : 'red' }}"
                                            class="px-2 py-1 cursor-pointer text-xs"
                                            wire:click="$parent.openPlanMetricEdit({{ $member['user_id'] }}, 'oneRepMax')"
                                        >
                                            {{ $member['name'] }}: {{ $member['label'] ?? '—' }}
                                        </flux:badge>
                                    @endforeach
                                </div>
                            @else
                                <flux:label class="mb-1.5">{{ __('1RM') }}</flux:label>
                                @if ($plan1rmLabel)
                                    <div>
                                        <flux:badge color="zinc" class="text-lg px-3 py-1.5 cursor-pointer" wire:click="$parent.openPlan1rmEdit">
                                            {{ $plan1rmLabel }}
                                        </flux:badge>
                                    </div>
                                @else
                                    <div>
                                        <flux:badge color="red" class="text-lg px-3 py-1.5 cursor-pointer" wire:click="$parent.openPlan1rmEdit">
                                            {{ __('Not set') }}
                                        </flux:badge>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif

                    @if ($hasHeartRateExercises)
                        <div>
                            @if ($userId === null)
                                <flux:label class="mb-1.5">{{ __('Heart Rate') }}</flux:label>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($planGroupMemberMetrics['heartRate'] ?? [] as $member)
                                        <flux:badge
                                            color="{{ $member['label'] ? 'zinc' : 'red' }}"
                                            class="px-2 py-1 cursor-pointer text-xs"
                                            wire:click="$parent.openPlanMetricEdit({{ $member['user_id'] }}, 'heartRate')"
                                        >
                                            {{ $member['name'] }}: {{ $member['label'] ?? '—' }}
                                        </flux:badge>
                                    @endforeach
                                </div>
                            @else
                                <flux:label class="mb-1.5">{{ __('Heart Rate') }}</flux:label>
                                @if ($planHeartRateLabel)
                                    <div>
                                        <flux:badge color="zinc" class="text-lg px-3 py-1.5 cursor-pointer" wire:click="$parent.openPlanHeartRateEdit">
                                            {{ $planHeartRateLabel }}
                                        </flux:badge>
                                    </div>
                                @else
                                    <div>
                                        <flux:badge color="red" class="text-lg px-3 py-1.5 cursor-pointer" wire:click="$parent.openPlanHeartRateEdit">
                                            {{ __('Not set') }}
                                        </flux:badge>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif
                </div>
            </x-cms::section>
        @endif

        @if ($showWeeksInput)
            <x-cms::section :title="__('Settings')" class="w-64 shrink-0">
                <flux:field>
                    <flux:label>{{ __('Weeks') }}</flux:label>
                    <flux:input wire:model.live="weeks" type="number" min="1" max="52" step="1" />
                </flux:field>
            </x-cms::section>
        @endif
    </div>

    @if ($this->exercises->isNotEmpty())
        <div class="{{ $gridLayout === 'stacked' ? 'grid grid-cols-1 gap-4' : 'grid grid-cols-1 lg:grid-cols-2 gap-4' }}" wire:key="grids-{{ $this->exercises->pluck('id')->implode('-') }}">
            @foreach ($this->exercises as $exercise)
                <div wire:key="grid-{{ $exercise->id }}-{{ $userId ?? 'default' }}" class="min-w-0">
                    <livewire:training.view.plan-exercise-grid
                        :key="'grid-' . $exercise->id . '-' . $weeks . '-' . ($userId ?? 'default')"
                        :exercisePlanId="$planId"
                        :planType="$planType"
                        :exerciseId="$exercise->id"
                        :groupLabel="$this->exerciseGroupLabels[$exercise->id] ?? null"
                        :userId="$userId"
                        :disabled="false"
                        :weeks="$weeks"
                        :sessionsPerWeek="$sessionsPerWeek"
                        :weekLabels="$weekLabels"
                        :weekSessions="$weekSessions"
                        :sessionLabels="$sessionLabels"
                        :planMeasuredReps="$planMeasuredReps"
                        :planMeasuredWeight="$planMeasuredWeight"
                        :planTargetGoal="$planTargetGoal"
                        :planMaxHR="$planMaxHR"
                        :planIatPercent="$planIatPercent"
                    />
                </div>
            @endforeach
        </div>

        <livewire:training.view.plan-exercise-settings-form />
    @else
        <flux:text class="text-zinc-500">{{ __('No exercises in this program. Add exercises above to see the training grids.') }}</flux:text>
    @endif
</div>
