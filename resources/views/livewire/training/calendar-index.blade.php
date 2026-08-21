<div class="flex {{ $this->showSidebar ? 'gap-6' : '' }}">
    @if ($this->showSidebar)
        <div class="hidden lg:block">
            <livewire:user-group-sidebar mode="single-athlete" :initial-group="$group !== '' ? (int) $group : null" :initial-user="$user !== '' ? (int) $user : null" :show-group-filter="true" :initial-group-filter="$groupFilter" />
        </div>
    @endif

    <div class="flex-1 min-w-0">
        <div class="{{ $this->showSidebar ? 'lg:hidden' : '' }} flex flex-col sm:flex-row items-stretch sm:items-end gap-4 pb-6">
            <div class="w-full sm:w-auto sm:shrink-0">
                <flux:label class="mb-2">{{ __('Groups') }}</flux:label>
                <flux:radio.group wire:model.live="groupFilter" variant="segmented" class="w-full sm:w-auto">
                    <flux:radio value="mine" :label="__('My Groups')" />
                    <flux:radio value="all" :label="__('All Groups')" />
                </flux:radio.group>
            </div>
            <flux:field class="w-full sm:min-w-[200px] sm:w-auto">
                <flux:label>{{ __('Group') }}</flux:label>
                <flux:select variant="listbox" searchable clearable wire:model.live="group" placeholder="{{ __('Select group...') }}">
                    @foreach ($this->groupOptions as $id => $name)
                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            @if ($group !== '')
                <flux:field class="w-full sm:min-w-[200px] sm:w-auto">
                    <flux:label>{{ __('Athlete') }}</flux:label>
                    <flux:select variant="listbox" searchable clearable wire:model.live="user" placeholder="{{ __('All athletes') }}">
                        @foreach ($this->athleteOptions as $id => $name)
                            <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            @endif
        </div>
        <div>
                @if ($this->hasSelection() && !$this->hasGroupAthletes)
                    {{-- No athletes --}}
                    <div class="flex items-center gap-3">
                        <flux:heading size="xl">{{ $this->selectionName }}</flux:heading>
                        <flux:badge size="lg" color="blue">{{ __('Group') }}</flux:badge>
                    </div>
                    <div class="flex flex-col items-center justify-center py-20 text-center">
                        <flux:icon.users class="size-10 text-zinc-300 dark:text-zinc-600 mb-3" />
                        <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">{{ __('No athletes in this group') }}</flux:heading>
                    </div>
                @elseif ($this->hasSelection() && $this->programs->isEmpty())
                    {{-- No programs --}}
                    <div class="flex items-center gap-3">
                        <flux:heading size="xl">{{ $this->selectionName }}</flux:heading>
                        <flux:badge size="lg" color="blue">{{ __('Group') }}</flux:badge>
                    </div>
                    <div class="flex flex-col items-center justify-center py-20 text-center">
                        <flux:icon.calendar class="size-10 text-zinc-300 dark:text-zinc-600 mb-3" />
                        <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">{{ __('No programs in this group') }}</flux:heading>
                        <flux:button variant="primary" icon="plus" size="sm" class="mt-3" wire:click="openAddProgram">{{ __('Add Program') }}</flux:button>
                    </div>
                @elseif ($this->hasSelection())
                    {{-- Row 1: Selection name + view switch --}}
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pb-8">
                        <div class="flex items-center gap-3 flex-wrap">
                            @if ($this->selectionGroupName)
                                <flux:heading size="xl" class="text-zinc-400 dark:text-zinc-500">{{ $this->selectionGroupName }}</flux:heading>
                                <flux:icon.chevron-right class="size-5 text-zinc-400 dark:text-zinc-500" />
                            @endif
                            <flux:heading size="xl">{{ $this->selectionName }}</flux:heading>
                            @if ($this->selectionType === 'athlete')
                                <flux:badge size="lg" color="green">{{ __('Athlete') }}</flux:badge>
                            @else
                                <flux:badge size="lg" color="blue">{{ __('Group') }}</flux:badge>
                            @endif
                        </div>
                        <div class="overflow-x-auto -mx-2 px-2">
                            <flux:radio.group wire:model.live="view" variant="segmented" size="lg" class="min-w-fit">
                                <flux:radio value="overview" :label="__('Overview')" icon="grid-2x2" />
                                <flux:radio value="schedule" :label="__('Schedule')" icon="calendar-plus" />
                                <flux:radio value="plan" :label="__('Plan')" icon="biceps-flexed" />
                            </flux:radio.group>
                        </div>
                    </div>

                    {{-- Row 2: View-dependent toolbar + content --}}
                    @if ($view === 'plan')
                        <div class="py-2 flex flex-col sm:flex-row sm:items-center gap-4" wire:key="plan-selects-{{ $planCategory }}-{{ $planBlock }}-{{ $planProgram }}">
                            <flux:field class="w-full sm:min-w-[180px] sm:w-auto">
                                <flux:label>{{ __('Category') }}</flux:label>
                                <flux:select variant="listbox" searchable size="sm" wire:model.live="planCategory">
                                    @foreach ($this->planCategoryOptions as $id => $name)
                                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                            <flux:field class="w-full sm:min-w-[180px] sm:w-auto">
                                <flux:label>{{ __('Block') }}</flux:label>
                                <flux:select variant="listbox" searchable size="sm" wire:model.live="planBlock">
                                    @foreach ($this->planBlockOptions as $id => $name)
                                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                            <flux:field class="w-full sm:min-w-[180px] sm:w-auto">
                                <flux:label>{{ __('Program') }}</flux:label>
                                <flux:select variant="listbox" searchable size="sm" wire:model.live="planProgram">
                                    @foreach ($this->planProgramOptions as $id => $name)
                                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                            @if ($this->planSelectedProgram)
                                <flux:field class="w-full sm:w-auto">
                                    <flux:label>{{ __('Status') }}</flux:label>
                                    <flux:radio.group wire:model.live="planProgramStatus" wire:change="savePlanProgramStatus" variant="segmented" size="sm" class="w-full sm:w-auto">
                                        <flux:radio value="active" :label="__('Active')" />
                                        <flux:radio value="archived" :label="__('Archived')" />
                                    </flux:radio.group>
                                </flux:field>
                            @endif
                        </div>

                        @if ($this->planSelectedProgram)
                            @if (! $this->planHasBlock && $this->planHasAutoWeightExercises)
                                <div class="py-3">
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
                                <div class="py-4">
                                    <livewire:training.view.program-editor
                                        :key="$this->planEditorRenderKey()"
                                        :exerciseProgram="$this->planSelectedProgram->program"
                                        :planId="$this->planSelectedProgram->program->id"
                                        :scheduledTrainingProgramId="$this->planSelectedProgram->id"
                                        :userId="$user !== '' ? (int) $user : null"
                                        :showWeeksInput="false"
                                        :weeks="$this->planScheduleInfo['weeks']"
                                        :sessionsPerWeek="$this->planScheduleInfo['sessionsPerWeek']"
                                        :weekLabels="$this->planScheduleInfo['weekLabels']"
                                        :weekSessions="$this->planScheduleInfo['weekSessions']"
                                        :weekSessionDates="$this->planScheduleInfo['weekSessionDates']"
                                        :weekSessionDateRanges="$this->planScheduleInfo['weekSessionDateRanges']"
                                        :expandedWeeks="$this->planScheduleInfo['expandedWeeks']"
                                        :lockedSessionsByWeek="$this->planScheduleInfo['lockedSessionsByWeek']"
                                        :sessionStatusesByWeek="$this->planScheduleInfo['sessionStatusesByWeek']"
                                        :exerciseSessionStatusesByWeek="$this->planScheduleInfo['exerciseSessionStatusesByWeek']"
                                        :calendarWeekSchedule="$this->planScheduleInfo['calendarWeekSchedule'] ?? []"
                                        :sessionLabels="true"
                                        :showActualValueTabs="true"
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
                    @else
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between pt-4 pb-2">
                            <flux:field class="sm:min-w-[440px]">
                                <flux:date-picker
                                    mode="range"
                                    size="sm"
                                    locale="de-DE"
                                    with-presets
                                    presets="thisWeek lastWeek thisMonth lastMonth thisQuarter lastQuarter nextQuarter next30Days next3Months next6Months custom"
                                    max-range="184"
                                    wire:model.live="range"
                                />
                            </flux:field>
                            <div class="flex items-center gap-2">
                                <x-training.status-legend position="left" />
                                @if ($view === 'overview')
                                    <flux:button variant="primary" icon="plus" size="sm" wire:click="openAddBlock">{{ __('Add Block') }}</flux:button>
                                    <flux:button variant="primary" icon="plus" size="sm" wire:click="openAddProgram">{{ __('Add Program') }}</flux:button>
                                @endif
                            </div>
                        </div>

                        @if ($view === 'schedule')
                            <livewire:training.calendar-schedule-view
                                :key="'calendar-schedule-' . ($group !== '' ? $group : 'none') . '-' . ($user !== '' ? $user : 'group') . '-' . ($calendarSettings->preset ?? 'custom') . '-' . ($calendarSettings->start ?? 'none') . '-' . ($calendarSettings->end ?? 'none')"
                                :groupId="(int) $group"
                                :userId="$user !== '' ? (int) $user : null"
                                :calendarSettings="$calendarSettings"
                                :weekStartsOn="$weekStartsOn"
                            />
                        @else
                            <livewire:training.calendar-programs-view
                                :key="'calendar-programs-' . ($group !== '' ? $group : 'none') . '-' . ($user !== '' ? $user : 'group') . '-' . ($calendarSettings->preset ?? 'custom') . '-' . ($calendarSettings->start ?? 'none') . '-' . ($calendarSettings->end ?? 'none')"
                                :groupId="(int) $group"
                                :userId="$user !== '' ? (int) $user : null"
                                :calendarSettings="$calendarSettings"
                                :weekStartsOn="$weekStartsOn"
                                :weekEndsOn="$weekEndsOn"
                            />
                        @endif

                    @endif
                @elseif ($this->hasOverviewGroups)
                    <flux:heading size="xl">{{ __('Summary') }}</flux:heading>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between pt-4 pb-2">
                        <flux:field class="sm:min-w-[440px]">
                            <flux:date-picker
                                mode="range"
                                size="sm"
                                locale="de-DE"
                                with-presets
                                presets="thisWeek lastWeek thisMonth lastMonth thisQuarter lastQuarter nextQuarter next30Days next3Months next6Months custom"
                                max-range="184"
                                wire:model.live="range"
                            />
                        </flux:field>
                        <div class="relative" x-data="{ legendOpen: false }">
                            <flux:button variant="ghost" size="sm" icon="information-circle" x-on:click="legendOpen = !legendOpen">
                                {{ __('Legend') }}
                            </flux:button>
                            <div x-show="legendOpen"
                                x-cloak
                                x-on:click.outside="legendOpen = false"
                                class="absolute right-0 top-full z-20 mt-2 w-56 rounded-xl border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                <div class="space-y-2 text-sm text-zinc-700 dark:text-zinc-200">
                                    @foreach ($this->exerciseCategoryLegend as $category)
                                        <div class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full shrink-0"
                                                style="{{ \Coda\Cms\Support\ColorPalette::solid($category->color) }}"></span>
                                            <span>{{ $category->name }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <livewire:training.calendar-overview-grid
                        :key="'calendar-overview-' . $groupFilter . '-' . ($calendarSettings->preset ?? 'custom') . '-' . ($calendarSettings->start ?? 'none') . '-' . ($calendarSettings->end ?? 'none')"
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
        </div>
    </div>
    <livewire:training.calendar-add-program-modal
        :key="'calendar-add-program-' . ($group !== '' ? $group : 'none') . '-' . ($user !== '' ? $user : 'group')"
        :groupId="(int) $group"
        :userId="$user !== '' ? (int) $user : null"
    />
    <livewire:training.block-form />

    <livewire:database.athlete-metric-form-modal
        name="calendar-metric-form"
        :title="__('Add Metric')"
        :formDataClass="App\Data\Athlete\Metric\MetricSubmissionData::class"
        :flyout="true"
        maxWidth="max-w-[83.333%] overflow-x-hidden"
        :showDelete="true"
    />

    <x-cms::confirm-modal
        name="confirm-delete-plan-metric"
        :heading="__('Delete Metric?')"
        :description="__('You\'re about to delete this metric. This action cannot be reversed.')"
        :confirmLabel="__('Delete')"
        action="deletePlanMetricSubmission"
    />
</div>
