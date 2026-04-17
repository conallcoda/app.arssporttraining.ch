<div>
    <x-athlete.page-tabs active="calendar">
        <x-athlete.page-tabs.row>
            <x-athlete.segmented-tabs :tabs="[
                [
                    'name' => 'day',
                    'label' => 'Day',
                    'icon' => 'calendar-1',
                    'href' => route('athlete.dashboard.calendar', ['date' => $this->selectedDateValue]),
                    'navigate' => true,
                    'selected' => $calendarView === 'day',
                ],
                [
                    'name' => 'week',
                    'label' => 'Week',
                    'icon' => 'calendar-range',
                    'href' => route('athlete.dashboard.calendar.week', ['date' => $this->selectedDateValue]),
                    'navigate' => true,
                    'selected' => $calendarView === 'week',
                ],
            ]" />
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

    <div class="w-full md:mx-auto md:max-w-[900px] px-0 py-0">
        @if ($calendarView === 'day')
            <livewire:athlete.day-schedule :date="$this->selectedDateValue" :show-readiness="false" />
        @elseif ($calendarView === 'week')
            <livewire:athlete.day-schedule :date="$this->selectedDateValue" view-mode="week" :show-readiness="false" />
        @endif
    </div>
</div>
