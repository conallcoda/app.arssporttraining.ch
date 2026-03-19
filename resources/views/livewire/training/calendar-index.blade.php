<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item current>{{ __('Calendar') }}</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <div class="flex gap-6">
        <livewire:user-group-sidebar mode="single-athlete" :initial-group="$group !== '' ? (int) $group : null" :initial-user="$user !== '' ? (int) $user : null" :show-group-filter="true" />

        <div class="flex-1 min-w-0">
            <x-section :title="__('Calendar')" class="!p-0">
                <div class="px-4 pt-3 pb-2 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:heading size="xl">
                            @if ($this->selectionName)
                                {{ $this->selectionName }}, {{ $this->title }}
                            @else
                                {{ $this->title }}
                            @endif
                        </flux:heading>
                        <flux:button variant="ghost" icon="pencil" size="sm" wire:click="openCalendarRange" />
                    </div>
                    @if ($this->hasSelection())
                        <flux:radio.group wire:model.live="view" variant="segmented" size="sm">
                            <flux:radio value="overview" :label="__('Overview')" />
                            <flux:radio value="schedule" :label="__('Schedule')" />
                            <flux:radio value="plan" :label="__('Plan')" />
                        </flux:radio.group>
                    @endif
                </div>

                @if ($this->hasSelection() && $this->programs->isNotEmpty())
                    @if ($view === 'overview')
                        <div class="px-4 py-2 flex justify-end">
                            <flux:button variant="primary" icon="plus" size="sm" wire:click="openAddContent">{{ __('Add Program') }}</flux:button>
                        </div>
                    @endif

                    @if ($view === 'plan')
                        <div class="px-4 py-2 flex items-center gap-4">
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
                                        :key="'plan-editor-' . $this->planSelectedProgram->id . '-' . $this->planScheduleInfo['weeks'] . '-' . ($user !== '' ? $user : 'group') . '-' . $planBlock . '-' . md5(json_encode($this->planMeasuredData))"
                                        :exerciseProgram="$this->planSelectedProgram->program"
                                        :planId="$this->planSelectedProgram->program->id"
                                        :userId="$user !== '' ? (int) $user : null"
                                        :showWeeksInput="false"
                                        :weeks="$this->planScheduleInfo['weeks']"
                                        :sessionsPerWeek="$this->planScheduleInfo['sessionsPerWeek']"
                                        :weekLabels="$this->planScheduleInfo['weekLabels']"
                                        :weekSessions="$this->planScheduleInfo['weekSessions']"
                                        :planMeasuredReps="$this->planMeasuredData['measuredReps']"
                                        :planMeasuredWeight="$this->planMeasuredData['measuredWeight']"
                                        :planTargetGoal="$this->planBlockGoal"
                                        gridLayout="stacked"
                                    />
                                </div>
                            @else
                                <div class="flex flex-col items-center justify-center py-20 text-center">
                                    <flux:icon.calendar class="size-10 text-zinc-300 dark:text-zinc-600 mb-3" />
                                    <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">{{ __('No sessions scheduled for this program') }}</flux:heading>
                                </div>
                            @endif
                        @endif
                    @elseif ($view === 'schedule')
                        <div class="px-4 py-2 flex items-center gap-4">
                            <flux:radio.group wire:model.live="weekEditMode" variant="segmented" size="sm">
                                <flux:radio value="view" :label="__('View')" />
                                <flux:radio value="edit" :label="__('Edit')" />
                                <flux:radio value="remove" :label="__('Remove')" />
                            </flux:radio.group>

                            @if ($weekEditMode === 'edit')
                                <div class="flex items-center gap-3 flex-1">
                                    <flux:select variant="listbox" searchable size="sm" wire:model.live="quickProgramId" :placeholder="__('Select program...')" :invalid="$errors->has('quickProgramId')">
                                        @foreach ($this->quickProgramOptions as $id => $name)
                                            <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>

                                    @if ($this->quickAthleteOptions->isNotEmpty())
                                        <flux:select variant="listbox" searchable multiple size="sm" wire:model.live="quickSelectedAthletes" :placeholder="__('Athletes...')" :invalid="$errors->has('quickSelectedAthletes')">
                                            @foreach ($this->quickAthleteOptions as $athlete)
                                                <flux:select.option value="{{ $athlete['id'] }}">{{ $athlete['name'] }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @include('livewire.training.partials.calendar-week-grid')
                    @else
                        @include('livewire.training.partials.calendar-programs-grid')
                    @endif
                @elseif ($this->hasSelection())
                    <div class="flex flex-col items-center justify-center py-20 text-center">
                        <flux:icon.calendar class="size-10 text-zinc-300 dark:text-zinc-600 mb-3" />
                        <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">{{ __('No programs assigned') }}
                        </flux:heading>
                        <flux:button variant="primary" icon="plus" size="sm" wire:click="openAddContent" class="mt-3">{{ __('Add Program') }}</flux:button>
                    </div>
                @else
                    @include('livewire.training.partials.calendar-overview-grid')
                @endif
            </x-section>
        </div>
    </div>

    <livewire:training.calendar-range-form />

    <livewire:training.week-slot-form />

    <livewire:training.block-form />

    <livewire:training.calendar-exercise-settings-form />

    <livewire:training.exercise-program-form-modal
        name="edit-program"
        :title="__('Edit Exercise Program')"
        :flyout="true"
        maxWidth="max-w-lg"
        :showDelete="true"
        :excludeFields="['owner_id', 'internalTags']"
    />

    <livewire:database.athlete-metric-form-modal
        name="calendar-metric-form"
        :title="__('Add Metric')"
        :formDataClass="App\Data\Athlete\Metric\MetricSubmissionData::class"
        :flyout="true"
        maxWidth="max-w-sm"
        :excludeFields="['recorded_at']"
    />

    <x-cms::confirm-modal
        name="confirm-delete-program"
        :heading="__('Remove program?')"
        :description="__('You\'re about to remove this program from the calendar. This action cannot be reversed.')"
        :confirmLabel="__('Delete')"
        action="deleteEditingTrainingProgram"
    />

    <flux:modal name="add-content" variant="flyout" class="max-w-md">
        <div class="flex flex-col gap-4 p-2">
            <flux:heading size="lg">{{ __('Add Program') }}</flux:heading>

            <flux:tab.group>
                <flux:tabs wire:model.live="addContentTab">
                    <flux:tab name="program">{{ __('Program') }}</flux:tab>
                    <flux:tab name="exercise">{{ __('Exercise') }}</flux:tab>
                </flux:tabs>

                <flux:tab.panel name="program" class="!px-0">
                    <div class="flex flex-col gap-3">
                        <x-cms::form.field :field="\Coda\Cms\Form\Fields\Search::make('addContentSearch')" />
                        <div class="flex flex-col gap-1 max-h-80 overflow-y-auto">
                            @foreach ($this->addContentOptions as $option)
                                <button type="button" wire:click="addFromProgram({{ $option->id }})"
                                    class="flex items-center gap-2 px-3 py-2 text-sm text-left rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                    @if ($option->exerciseCategory?->color)
                                        <span class="w-2 h-2 rounded-full shrink-0"
                                            style="{{ \Coda\Cms\Support\ColorPalette::solid($option->exerciseCategory->color) }}"></span>
                                    @endif
                                    {{ $option->name }}
                                </button>
                            @endforeach
                            @if ($this->addContentOptions->isEmpty())
                                <flux:text class="px-3 py-4 text-center text-zinc-400">{{ __('No programs found.') }}</flux:text>
                            @endif
                        </div>
                    </div>
                </flux:tab.panel>

                <flux:tab.panel name="exercise" class="!px-0">
                    <div class="flex flex-col gap-3">
                        <x-cms::form.field :field="\Coda\Cms\Form\Fields\Search::make('addContentSearch')" />
                        <div class="flex flex-col gap-1 max-h-80 overflow-y-auto">
                            @foreach ($this->addContentOptions as $option)
                                <button type="button" wire:click="addFromExercise({{ $option->id }})"
                                    class="flex items-center gap-2 px-3 py-2 text-sm text-left rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                    @if ($option->category?->rootAncestorOrSelf?->color)
                                        <span class="w-2 h-2 rounded-full shrink-0"
                                            style="{{ \Coda\Cms\Support\ColorPalette::solid($option->category->rootAncestorOrSelf->color) }}"></span>
                                    @endif
                                    {{ $option->name }}
                                </button>
                            @endforeach
                            @if ($this->addContentOptions->isEmpty())
                                <flux:text class="px-3 py-4 text-center text-zinc-400">{{ __('No exercises found.') }}</flux:text>
                            @endif
                        </div>
                    </div>
                </flux:tab.panel>
            </flux:tab.group>
        </div>
    </flux:modal>
</flux:main>
