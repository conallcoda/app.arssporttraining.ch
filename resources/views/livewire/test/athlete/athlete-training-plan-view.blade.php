<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('athlete.dashboard') }}" separator="slash" wire:navigate>Dashboard</flux:breadcrumbs.item>
        <flux:breadcrumbs.item separator="slash">{{ $trainingPlan->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if ($this->selectedAthleteId && count($this->scheduleWeeks) > 0)
        <div class="flex items-center justify-between">
            @if ($viewMode === 'list')
                <div class="flex items-end gap-4">
                    <flux:select wire:model.live="selectedWeek" label="Week">
                        @foreach ($this->weekOptions as $value => $label)
                            <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="selectedDay" label="Day">
                        @foreach ($this->dayOptions as $value => $label)
                            <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @else
                <div></div>
            @endif

            <div class="flex gap-1">
                <flux:button wire:click="$set('viewMode', 'list')" variant="{{ $viewMode === 'list' ? 'primary' : 'ghost' }}" size="sm" icon="list" square />
                <flux:button wire:click="$set('viewMode', 'calendar')" variant="{{ $viewMode === 'calendar' ? 'primary' : 'ghost' }}" size="sm" icon="table" square />
            </div>
        </div>

        @if ($viewMode === 'list')
            @forelse ($this->slotContent as $slotIndex => $slot)
                <div wire:key="slot-{{ $slotIndex }}" class="space-y-4">
                    <flux:heading size="lg">{{ $slot['heading'] }}</flux:heading>

                    @foreach ($slot['programs'] as $program)
                        <div wire:key="slot-{{ $slotIndex }}-program-{{ $program['id'] }}" class="space-y-2 ml-4">
                            <flux:heading level="3">{{ $program['name'] }}</flux:heading>

                            @if (!empty($program['exercises']))
                                <div class="space-y-1 ml-4">
                                    @foreach ($program['exercises'] as $exercise)
                                        <flux:text>{{ $exercise['name'] }}</flux:text>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @empty
                <flux:text class="text-zinc-500 dark:text-zinc-400">No sessions scheduled for this day.</flux:text>
            @endforelse
        @else
            <livewire:test.athlete.athlete-schedule-grid :trainingPlan="$trainingPlan" :key="'grid-' . ($this->selectedAthleteId ?? 'none')" />
        @endif
    @elseif (!$this->selectedAthleteId)
        <flux:text class="text-zinc-500 dark:text-zinc-400">Select an athlete to view their training plan.</flux:text>
    @else
        <flux:text class="text-zinc-500 dark:text-zinc-400">No schedule configured for this training plan.</flux:text>
    @endif
</div>
