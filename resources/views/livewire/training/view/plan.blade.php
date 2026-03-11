<div class="space-y-6 focus:outline-none">
    @if ($this->scheduledPrograms->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach ($this->scheduledPrograms as $program)
                @php
                    $color = $program->exerciseCategory?->color ?? 'zinc';
                    $isSelected = $selectedProgramId === $program->id;
                @endphp
                @if ($isSelected)
                    <flux:badge
                        wire:key="prog-{{ $program->id }}"
                        wire:click="selectProgram({{ $program->id }})"
                        as="button"
                        variant="solid"
                        color="{{ $color }}"
                    >
                        {{ $program->name }}
                    </flux:badge>
                @else
                    <flux:badge
                        wire:key="prog-{{ $program->id }}"
                        wire:click="selectProgram({{ $program->id }})"
                        as="button"
                        color="{{ $color }}"
                    >
                        {{ $program->name }}
                    </flux:badge>
                @endif
            @endforeach
        </div>

        @if ($this->selectedProgram)
            <livewire:training.view.program-editor
                :key="'editor-' . $selectedProgramId . '-' . $this->weeks"
                :exerciseProgram="$this->selectedProgram"
                :weeks="$this->weeks"
                :showWeeksInput="false"
                :sessionsPerWeek="$this->sessionsPerWeekByProgram[$selectedProgramId] ?? 1"
                :userId="null"
                :planMeasuredReps="1"
                :planMeasuredWeight="50"
                :planTargetGoal="10"
                :planType="get_class($exercisePlan)"
                :planId="$exercisePlan->id"
            />
        @endif
    @else
        <flux:text class="text-zinc-500">{{ __('No programs in the schedule. Add programs in the Schedule tab.') }}</flux:text>
    @endif

    {{-- TODO: deprecated - user sidebar --}}
    {{--
    @if ($users->isNotEmpty())
        <x-section title="Plans" class="w-64 shrink-0 sticky top-4 self-start">
            ...
        </x-section>
    @endif
    --}}

    {{-- TODO: deprecated - target and schedule sections --}}
    {{--
    <div class="flex gap-6">
        <x-section title="Target" class="w-[55%]">
            ...
        </x-section>
        <x-section title="Schedule" class="w-[45%]">
            ...
        </x-section>
    </div>
    --}}

    {{-- TODO: deprecated - reset modals --}}
    {{--
    <flux:modal name="reset-default-settings" class="min-w-[22rem]">...</flux:modal>
    <flux:modal name="reset-user-settings" class="min-w-[22rem]">...</flux:modal>
    <flux:modal name="reset-selected-user-settings" class="min-w-[22rem]">...</flux:modal>
    --}}
</div>
