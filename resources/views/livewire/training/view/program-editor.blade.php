<div class="space-y-6">
    @php
        $showActualDisplayToggle = $showActualValueTabs && $userId !== null;
        $showSettingsSection = $showWeeksInput || $showActualDisplayToggle;
        $stackSidebar = $gridLayout === 'stacked' && ($this->showAthleteContext || $showSettingsSection);
    @endphp

    <div class="flex flex-col md:flex-row gap-6">
        <x-cms::section :title="__('Exercises')" class="self-start {{ $stackSidebar ? 'md:flex-1' : ($this->showAthleteContext ? 'md:w-3/4' : 'flex-1') }}">
            @if ($showNameInput)
                <flux:input wire:model="$parent.planProgramName" wire:blur="$parent.savePlanProgramName" :label="__('Name')" size="sm" />
            @endif

            <div class="space-y-4">
                @if ($this->showSectionTabs)
                    <flux:tabs wire:model.live="activeSection" variant="segmented" class="w-full [&>[data-flux-tabs]]:grid [&>[data-flux-tabs]]:w-full [&>[data-flux-tabs]]:grid-cols-3">
                        <flux:tab name="main">{{ __('Main') }}</flux:tab>
                        <flux:tab name="warm_up">{{ __('Warm Up') }}</flux:tab>
                        <flux:tab name="warm_down">{{ __('Cool Down') }}</flux:tab>
                    </flux:tabs>
                @endif
            </div>

            @foreach ($this->fieldsets as $item)
                <x-form-kit::form.fieldset
                    :fieldset="$item"
                    :prefix="$item->prefix ?? 'data'"
                    :showLegend="false"
                />
            @endforeach
        </x-cms::section>

        @if ($stackSidebar)
            <div class="md:w-80 lg:w-96 shrink-0 space-y-6">
                @if ($this->showAthleteContext)
                    <x-cms::section :title="$userId === null ? __('Group') : __('Athlete')">
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

                @if ($showSettingsSection)
                    <x-cms::section :title="__('Settings')">
                        <div class="space-y-4">
                            @if ($showActualDisplayToggle)
                                <div>
                                    <flux:label class="mb-1.5">{{ __('Display') }}</flux:label>
                                <div>
                                        <flux:radio.group wire:model.live="valueDisplayMode" variant="segmented" class="w-fit">
                                            <flux:radio value="planned" :label="__('Plan')" />
                                            <flux:radio value="actual" :label="__('Plan + Actual')" />
                                        </flux:radio.group>
                                    </div>

                                    @if ($userId !== null)
                                        <div class="mt-3">
                                            <flux:button variant="primary" icon="eye" wire:click="openPreviewModal">
                                                {{ __('Preview') }}
                                            </flux:button>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            @if ($showWeeksInput)
                                <flux:field>
                                    <flux:label>{{ __('Planned Weeks') }}</flux:label>
                                    <flux:input wire:model.live="weeks" type="number" min="1" max="52" step="1" />
                                </flux:field>
                            @endif
                        </div>
                    </x-cms::section>
                @endif
            </div>
        @else
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

            @if ($showSettingsSection)
                <x-cms::section :title="__('Settings')" class="w-80 shrink-0">
                    <div class="space-y-4">
                        @if ($showActualDisplayToggle)
                            <div>
                                <flux:label class="mb-1.5">{{ __('Display') }}</flux:label>
                            <div>
                                    <flux:radio.group wire:model.live="valueDisplayMode" variant="segmented" class="w-fit">
                                        <flux:radio value="planned" :label="__('Plan')" />
                                        <flux:radio value="actual" :label="__('Plan + Actual')" />
                                    </flux:radio.group>
                                </div>

                                @if ($userId !== null)
                                    <div class="mt-3">
                                        <flux:button variant="primary" icon="eye" wire:click="openPreviewModal">
                                            {{ __('Preview') }}
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        @endif
                        @if ($showWeeksInput)
                            <flux:field>
                                <flux:label>{{ __('Planned Weeks') }}</flux:label>
                                <flux:input wire:model.live="weeks" type="number" min="1" max="52" step="1" />
                            </flux:field>
                        @endif
                    </div>
                </x-cms::section>
            @endif
        @endif
    </div>

    @if ($this->exercises->isNotEmpty())
        <div class="grid grid-cols-1 gap-4" wire:key="grids-{{ $this->activeSection }}-{{ $valueDisplayMode }}-{{ $gridRenderVersion }}-{{ $this->exercises->pluck('pivot.id')->implode('-') }}">
            @foreach ($this->exercises as $exercise)
                <div wire:key="grid-{{ $exercise->pivot->id }}-{{ $userId ?? 'default' }}-{{ $valueDisplayMode }}-{{ $gridRenderVersion }}" class="min-w-0">
                    <livewire:training.view.plan-exercise-grid
                        :key="'grid-' . $exercise->pivot->id . '-' . $weeks . '-' . ($userId ?? 'default') . '-' . $valueDisplayMode . '-' . $gridRenderVersion"
                        :planId="$planId"
                        :scheduledTrainingProgramId="$scheduledTrainingProgramId"
                        :planConfigArray="$exerciseProgram->config->toArray()"
                        :programExerciseId="$exercise->pivot->id"
                        :exerciseId="$exercise->id"
                        :exerciseName="$exercise->name"
                        :exerciseConfigArray="$exercise->config->toArray()"
                        :exerciseBadges="$this->exerciseBadgesByPivotId[$exercise->pivot->id] ?? []"
                        :programExerciseSort="(int) ($exercise->pivot->sort ?? 0)"
                        :programExerciseType="(string) ($exercise->pivot->type ?? 'main')"
                        :programExerciseGroup="$exercise->pivot->group"
                        :groupLabel="$this->exerciseGroupLabels[$exercise->pivot->id] ?? null"
                        :userId="$userId"
                        :weeks="$weeks"
                        :sessionsPerWeek="$sessionsPerWeek"
                        :weekLabels="$weekLabels"
                        :weekSessions="$weekSessions"
                        :weekSessionDates="$weekSessionDates"
                        :expandedWeeks="$expandedWeeks"
                        :lockedSessionsByWeek="$lockedSessionsByWeek"
                        :sessionLabels="$sessionLabels"
                        :showActualValueTabs="$showActualValueTabs"
                        :valueDisplayMode="$valueDisplayMode"
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
        <flux:text class="text-zinc-500">{{ __('No exercises in this section. Add exercises above to see the training grids.') }}</flux:text>
    @endif

    @if ($userId !== null)
        <flux:modal :name="$this->previewModalName()" class="w-[96vw] max-w-none sm:max-w-5xl">
            <div class="space-y-4">
                <div class="flex items-center justify-between gap-3 px-1">
                    <div>
                        <flux:heading size="lg">{{ __('Athlete Preview') }}</flux:heading>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <x-athlete.category-badge
                                :label="$exerciseProgram->name"
                                :color="$exerciseProgram->exerciseCategory?->color"
                                class="normal-case text-sm"
                            />

                            @if ($this->previewAthlete)
                                <flux:badge color="zinc" size="sm" class="px-2.5 py-1 text-sm">
                                    {{ $this->previewAthlete->forename }} {{ $this->previewAthlete->surname }}
                                </flux:badge>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($previewTrainingProgramId)
                    <livewire:athlete.day-schedule
                        :key="'athlete-preview-' . $previewTrainingProgramId . '-' . $weeks . '-' . $userId . '-v' . $previewOpenVersion"
                        :date="($weekSessionDates[0][0] ?? now()->toDateString())"
                        :show-readiness="false"
                        :preview-mode="true"
                        :preview-training-program-id="$previewTrainingProgramId"
                        :preview-user-id="$userId"
                        :initial-session-key="$previewInitialSessionKey"
                        :initial-section="$previewInitialSection"
                        :initial-exercise-id="$previewInitialExerciseId"
                        :initial-exercise-sort="$previewInitialExerciseSort"
                    />
                @endif
            </div>
        </flux:modal>
    @endif
</div>
