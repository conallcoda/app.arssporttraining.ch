<div class="space-y-6">
    @php
        $plannedGroupsLabel = match (data_get($data, 'session_grouping.mode')) {
            'none' => __('Planned Sessions'),
            'week' => __('Planned Weeks'),
            default => __('Planned Groups'),
        };
        $showSettingsSection = $showWeeksInput || $this->sessionGroupingFieldset;
        $stackSidebar = $gridLayout === 'stacked' && ($this->showAthleteContext || $showSettingsSection);
    @endphp

    <div class="flex flex-col md:flex-row gap-6">
        <x-cms::section :title="__('Exercises')" class="{{ $stackSidebar ? 'md:flex-1' : ($this->showAthleteContext ? 'md:w-3/4' : 'flex-1') }}">
            @if ($showNameInput)
                <flux:input wire:model="$parent.planProgramName" wire:blur="$parent.savePlanProgramName" :label="__('Name')" size="sm" />
            @endif

            <div class="space-y-4">
                @if ($this->showSectionTabs)
                    <flux:tabs wire:model.live="activeSection" variant="segmented" class="w-full [&>[data-flux-tabs]]:grid [&>[data-flux-tabs]]:w-full [&>[data-flux-tabs]]:grid-cols-3">
                        <flux:tab name="main">{{ __('Main') }}</flux:tab>
                        <flux:tab name="warm_up">{{ __('Warm Up') }}</flux:tab>
                        <flux:tab name="warm_down">{{ __('Warm Down') }}</flux:tab>
                    </flux:tabs>
                @endif

                <div class="flex flex-col gap-3 xl:flex-row xl:items-end">
                    <div class="flex-1">
                        <flux:field>
                            <flux:label>{{ __('Import Exercises From') }}</flux:label>
                            <flux:select wire:model.live="importProgramId" variant="listbox" searchable placeholder="{{ __('Select program...') }}">
                                @foreach ($this->importProgramOptions as $id => $name)
                                    <flux:select.option :value="$id">{{ $name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    </div>

                    <div class="shrink-0">
                        <flux:button wire:click="importSectionExercises" variant="primary" :disabled="blank($importProgramId)">
                            {{ __('Import') }}
                        </flux:button>
                    </div>
                </div>
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
                            @if ($this->sessionGroupingFieldset)
                                <x-form-kit::form.fieldset
                                    :fieldset="$this->sessionGroupingFieldset"
                                    :prefix="$this->sessionGroupingFieldset->prefix ?? 'data'"
                                    :showLegend="false"
                                />
                            @endif

                            @if ($showWeeksInput)
                                <flux:field>
                                    <flux:label>{{ $plannedGroupsLabel }}</flux:label>
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
                        @if ($this->sessionGroupingFieldset)
                            <x-form-kit::form.fieldset
                                :fieldset="$this->sessionGroupingFieldset"
                                :prefix="$this->sessionGroupingFieldset->prefix ?? 'data'"
                                :showLegend="false"
                            />
                        @endif

                        @if ($showWeeksInput)
                            <flux:field>
                                <flux:label>{{ $plannedGroupsLabel }}</flux:label>
                                <flux:input wire:model.live="weeks" type="number" min="1" max="52" step="1" />
                            </flux:field>
                        @endif
                    </div>
                </x-cms::section>
            @endif
        @endif
    </div>

    @if ($this->exercises->isNotEmpty())
        <div class="{{ $gridLayout === 'stacked' ? 'grid grid-cols-1 gap-4' : 'grid grid-cols-1 lg:grid-cols-2 gap-4' }}" wire:key="grids-{{ $this->activeSection }}-{{ $this->exercises->pluck('pivot.id')->implode('-') }}">
            @foreach ($this->exercises as $exercise)
                <div wire:key="grid-{{ $exercise->pivot->id }}-{{ $userId ?? 'default' }}" class="min-w-0">
                    <livewire:training.view.plan-exercise-grid
                        :key="'grid-' . $exercise->pivot->id . '-' . $weeks . '-' . ($userId ?? 'default')"
                        :exercisePlanId="$planId"
                        :planType="$planType"
                        :programExerciseId="$exercise->pivot->id"
                        :exerciseId="$exercise->id"
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
        <livewire:training.view.plan-exercise-grouping-form />
    @else
        <flux:text class="text-zinc-500">{{ __('No exercises in this section. Add exercises above to see the training grids.') }}</flux:text>
    @endif

    <x-cms::confirm-modal
        :name="$this->importConfirmModalName()"
        :heading="__('Import exercises?')"
        :description="__('This will replace all current exercises and their settings. This action cannot be undone.')"
        :confirmLabel="__('Import')"
        action="confirmImportSectionExercises"
        variant="primary"
    />
</div>
