<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item current>{{ __('Calendar') }}</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <div class="flex gap-6">
        <livewire:user-group-sidebar mode="single-athlete" :initial-group="$group !== '' ? (int) $group : null" :initial-user="$user !== '' ? (int) $user : null" :show-group-filter="true" :initial-group-filter="$groupFilter" />

        <div class="flex-1 min-w-0">
            <x-section :title="__('Calendar')" class="!p-0">
                <div class="px-4 pt-3 pb-2 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:heading size="xl">
                            @if ($view === 'plan')
                                {{ $this->selectionName ?? __('Calendar') }}
                            @elseif ($this->selectionName)
                                {{ $this->selectionName }}, {{ $this->title }}
                            @else
                                {{ $this->title }}
                            @endif
                        </flux:heading>
                        @if ($view !== 'plan')
                            <flux:button variant="ghost" icon="pencil" size="sm" wire:click="openCalendarRange" />
                        @endif
                    </div>
                    @if ($this->hasSelection())
                        <flux:radio.group wire:model.live="view" variant="segmented" size="sm">
                            <flux:radio value="overview" :label="__('Overview')" />
                            <flux:radio value="schedule" :label="__('Schedule')" />
                            <flux:radio value="plan" :label="__('Plan')" />
                        </flux:radio.group>
                    @endif
                </div>

                @if ($this->hasSelection())
                    @if ($view === 'plan' && $this->programs->isNotEmpty())
                        <div class="px-4 py-2 flex items-center gap-4" wire:key="plan-selects-{{ $planCategory }}-{{ $planBlock }}-{{ $planProgram }}">
                            <flux:field class="min-w-[180px]">
                                <flux:label>{{ __('Category') }}</flux:label>
                                <flux:select variant="listbox" searchable size="sm" wire:model.live="planCategory">
                                    @foreach ($this->planCategoryOptions as $id => $name)
                                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                            <flux:field class="min-w-[180px]">
                                <flux:label>{{ __('Block') }}</flux:label>
                                <flux:select variant="listbox" searchable size="sm" wire:model.live="planBlock">
                                    @foreach ($this->planBlockOptions as $id => $name)
                                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                            <flux:field class="min-w-[180px]">
                                <flux:label>{{ __('Program') }}</flux:label>
                                <flux:select variant="listbox" searchable size="sm" wire:model.live="planProgram">
                                    @foreach ($this->planProgramOptions as $id => $name)
                                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                        </div>

                        @if ($this->planSelectedProgram)
                            @if (! $this->planHasBlock && $this->planHasAutoWeightExercises)
                                <div class="px-4 py-3">
                                    <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-600 dark:text-amber-400">
                                        <p class="font-medium">{{ __('This program contains one or more exercises that involve automatic 1RM calculations. Please make sure that:') }}</p>
                                        <ul class="mt-2 list-disc list-inside space-y-1">
                                            <li>{{ __('You have scheduled this program within a block that has a target progression goal.') }}</li>
                                            <li>{{ __('Every athlete has a base 1RM measurement.') }}</li>
                                        </ul>
                                    </div>
                                </div>
                            @endif
                            @if ($this->planScheduleInfo['scheduled'])
                                <div class="px-4 py-4">
                                    <livewire:training.view.program-editor
                                        :key="'plan-editor-' . $this->planSelectedProgram->id . '-' . $this->planScheduleInfo['weeks'] . '-' . ($user !== '' ? $user : 'group') . '-' . $planBlock . '-' . $this->planBlockGoal . '-' . md5(json_encode($this->planMeasuredData)) . '-' . md5(json_encode($this->planHeartRateData)) . '-' . ($user === '' ? md5(json_encode($this->planGroupMemberMetrics)) : '')"
                                        :exerciseProgram="$this->planSelectedProgram->program"
                                        :planId="$this->planSelectedProgram->program->id"
                                        :userId="$user !== '' ? (int) $user : null"
                                        :showWeeksInput="false"
                                        :weeks="$this->planScheduleInfo['weeks']"
                                        :sessionsPerWeek="$this->planScheduleInfo['sessionsPerWeek']"
                                        :weekLabels="$this->planScheduleInfo['weekLabels']"
                                        :weekSessions="$this->planScheduleInfo['weekSessions']"
                                        :sessionLabels="true"
                                        :showNameInput="true"
                                        :planMeasuredReps="$this->planMeasuredData['measuredReps']"
                                        :planMeasuredWeight="$this->planMeasuredData['measuredWeight']"
                                        :planTargetGoal="$this->planBlockGoal"
                                        :planMaxHR="$this->planHeartRateData['maxHR']"
                                        :planIatPercent="$this->planHeartRateData['iatPercent']"
                                        gridLayout="stacked"
                                        :planBlockGoalLabel="$this->planBlockGoal ? $this->planBlockGoal . '%' : null"
                                        :plan1rmLabel="$this->plan1rmLabel"
                                        :planHeartRateLabel="$this->planHeartRateLabel"
                                        :hasAutoWeightExercises="$this->planHasAutoWeightExercises"
                                        :hasHeartRateExercises="$this->planHasHeartRateExercises"
                                        :planHasBlock="$this->planHasBlock"
                                        :planGroupMemberMetrics="$user === '' ? $this->planGroupMemberMetrics : []"
                                    />
                                </div>
                            @else
                                <div class="flex flex-col items-center justify-center py-20 text-center">
                                    <flux:icon.calendar class="size-10 text-zinc-300 dark:text-zinc-600 mb-3" />
                                    <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">{{ __('No sessions scheduled for this program') }}</flux:heading>
                                </div>
                            @endif
                        @endif
                    @elseif ($view === 'schedule' && $this->programs->isNotEmpty())
                        <livewire:training.calendar-schedule-view
                            :groupId="(int) $group"
                            :userId="$user !== '' ? (int) $user : null"
                            :calendarSettings="$calendarSettings"
                            :weekStartsOn="$weekStartsOn"
                        />
                    @elseif (!$this->hasGroupAthletes)
                        <div class="flex flex-col items-center justify-center py-20 text-center">
                            <flux:icon.users class="size-10 text-zinc-300 dark:text-zinc-600 mb-3" />
                            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">{{ __('No athletes in this group') }}</flux:heading>
                        </div>
                    @else
                        <livewire:training.calendar-programs-view
                            :groupId="(int) $group"
                            :userId="$user !== '' ? (int) $user : null"
                            :calendarSettings="$calendarSettings"
                            :weekStartsOn="$weekStartsOn"
                            :weekEndsOn="$weekEndsOn"
                        />
                    @endif
                @elseif ($this->hasOverviewGroups)
                    <livewire:training.calendar-overview-grid
                        :groupFilter="$groupFilter"
                        :calendarSettings="$calendarSettings"
                        :weekStartsOn="$weekStartsOn"
                        :weekEndsOn="$weekEndsOn"
                    />
                @else
                    <div class="flex flex-col items-center justify-center py-20 text-center">
                        <flux:icon.users class="size-10 text-zinc-300 dark:text-zinc-600 mb-3" />
                        @if ($groupFilter === 'mine')
                            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">{{ __('You don\'t own any groups yet') }}</flux:heading>
                            <flux:text class="mt-1 text-zinc-400 dark:text-zinc-500">{{ __('Switch to "All Groups" to see all available groups, or create a group to get started.') }}</flux:text>
                        @else
                            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">{{ __('No groups found') }}</flux:heading>
                        @endif
                    </div>
                @endif
            </x-section>
        </div>
    </div>

    <livewire:training.calendar-range-form />

    <livewire:training.block-form />

    <livewire:database.athlete-metric-form-modal
        name="calendar-metric-form"
        :title="__('Add Metric')"
        :formDataClass="App\Data\Athlete\Metric\MetricSubmissionData::class"
        :flyout="true"
        maxWidth="max-w-sm"
        :showDelete="true"
        :excludeFields="['recorded_at']"
    />

    <x-cms::confirm-modal
        name="confirm-delete-plan-metric"
        :heading="__('Delete Metric?')"
        :description="__('You\'re about to delete this metric. This action cannot be reversed.')"
        :confirmLabel="__('Delete')"
        action="deletePlanMetricSubmission"
    />
</flux:main>
