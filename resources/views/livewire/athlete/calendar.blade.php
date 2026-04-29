<div>
    <x-athlete.page-tabs>
        <x-athlete.page-tabs.row>
            <x-athlete.segmented-tabs :tabs="[
                [
                    'name' => 'day',
                    'label' => 'Day',
                    'icon' => 'calendar-1',
                    'href' => route('athlete.dashboard.calendar', ['date' => $this->selectedDayDateValue]),
                    'navigate' => true,
                    'selected' => $calendarView === 'day',
                ],
                [
                    'name' => 'week',
                    'label' => 'Week',
                    'icon' => 'calendar-range',
                    'href' => route('athlete.dashboard.calendar.week', ['date' => $this->selectedWeekDateValue]),
                    'navigate' => true,
                    'selected' => $calendarView === 'week',
                ],
                [
                    'name' => 'unrecorded',
                    'label' => 'Unrecorded',
                    'icon' => 'list',
                    'href' => route('athlete.dashboard.calendar.unrecorded'),
                    'navigate' => true,
                    'selected' => $calendarView === 'unrecorded',
                ],
            ]" />
        </x-athlete.page-tabs.row>

        @if ($calendarView === 'day')
            <x-athlete.page-tabs.row>
                <x-athlete.toolbar-navigator :previous-href="$this->previousDayUrl" :next-href="$this->nextDayUrl">
                    <flux:date-picker size="sm" locale="de-DE" wire:model.live="selectedDayDate" class="w-full" />
                </x-athlete.toolbar-navigator>
            </x-athlete.page-tabs.row>
        @elseif ($calendarView === 'week')
            <x-athlete.page-tabs.row>
                <x-athlete.toolbar-navigator :previous-href="$this->previousWeekUrl" :next-href="$this->nextWeekUrl">
                    <flux:date-picker size="sm" locale="de-DE" wire:model.live="selectedWeekDate" class="w-full" />
                </x-athlete.toolbar-navigator>
            </x-athlete.page-tabs.row>
        @endif
    </x-athlete.page-tabs>

    <div class="w-full md:mx-auto md:max-w-[900px] px-0 py-0">
        @if ($calendarView === 'day')
            <livewire:athlete.day-schedule :date="$this->selectedDayDateValue" :show-readiness="true" />
        @elseif ($calendarView === 'week')
            <livewire:athlete.day-schedule :date="$this->selectedWeekDateValue" view-mode="week" :show-readiness="false" />
        @elseif ($calendarView === 'unrecorded')
            <livewire:athlete.day-schedule :date="$dashboardDate" :show-readiness="false" schedule-filter="unrecorded" />
        @endif
    </div>
</div>
