<div>
    <x-athlete.page-tabs active="calendar">
        <x-athlete.page-tabs.row>
            <flux:tabs variant="segmented" class="w-full [&>[data-flux-tabs]]:grid [&>[data-flux-tabs]]:w-full [&>[data-flux-tabs]]:grid-cols-2">
                <flux:tab
                    name="day"
                    icon="calendar-1"
                    href="{{ route('athlete.dashboard.calendar', ['date' => $this->selectedDateValue]) }}"
                    wire:navigate
                    :selected="$calendarView === 'day'"
                >
                    Day
                </flux:tab>
                <flux:tab
                    name="week"
                    icon="calendar-range"
                    href="{{ route('athlete.dashboard.calendar.week', ['date' => $this->selectedDateValue]) }}"
                    wire:navigate
                    :selected="$calendarView === 'week'"
                >
                    Week
                </flux:tab>
            </flux:tabs>
        </x-athlete.page-tabs.row>

        @if ($calendarView === 'day')
            <x-athlete.page-tabs.row>
                <x-athlete.toolbar-navigator :previous-href="$this->previousDayUrl" :next-href="$this->nextDayUrl">
                    <flux:date-picker size="sm" locale="de-DE" wire:model.live="selectedDate" class="w-full" />
                </x-athlete.toolbar-navigator>
            </x-athlete.page-tabs.row>
        @elseif ($calendarView === 'week')
            <x-athlete.page-tabs.row>
                <x-athlete.toolbar-navigator :previous-href="$this->previousWeekUrl" :next-href="$this->nextWeekUrl">
                    <flux:date-picker size="sm" locale="de-DE" wire:model.live="selectedDate" class="w-full" />
                </x-athlete.toolbar-navigator>
            </x-athlete.page-tabs.row>
        @endif
    </x-athlete.page-tabs>

    <div class="mx-auto max-w-2xl px-2 py-6 sm:px-4 sm:py-8">
        @if ($calendarView === 'day')
            <livewire:athlete.day-schedule :date="$this->selectedDateValue" :show-readiness="false" />
        @elseif ($calendarView === 'week')
            <livewire:athlete.day-schedule :date="$this->selectedDateValue" view-mode="week" :show-readiness="false" />
        @endif
    </div>
</div>
