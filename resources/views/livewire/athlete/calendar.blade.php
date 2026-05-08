<div>
    <x-athlete.page-tabs>
        <x-athlete.page-tabs.row>
            <x-athlete.segmented-tabs :tabs="[
                [
                    'name' => 'train',
                    'label' => 'Train',
                    'icon' => 'biceps-flexed',
                    'href' => route('athlete.dashboard.train', ['date' => $this->selectedTrainDateValue]),
                    'navigate' => true,
                    'selected' => $dashboardMode === 'train',
                ],
                [
                    'name' => 'schedule',
                    'label' => 'Schedule',
                    'icon' => 'calendar-range',
                    'href' => route('athlete.dashboard.schedule', ['date' => $this->selectedScheduleDateValue]),
                    'navigate' => true,
                    'selected' => $dashboardMode === 'schedule',
                ],
                [
                    'name' => 'unrecorded',
                    'label' => 'Unrecorded',
                    'icon' => 'list',
                    'href' => route('athlete.dashboard.unrecorded'),
                    'navigate' => true,
                    'selected' => $dashboardMode === 'unrecorded',
                ],
            ]" />
        </x-athlete.page-tabs.row>

        @if ($dashboardMode === 'train')
            <x-athlete.page-tabs.row>
                <x-athlete.toolbar-navigator :previous-href="$this->previousTrainUrl" :next-href="$this->nextTrainUrl">
                    <flux:date-picker size="sm" locale="de-DE" wire:model.live="selectedTrainDate" class="w-full" />
                </x-athlete.toolbar-navigator>
            </x-athlete.page-tabs.row>
        @elseif ($dashboardMode === 'schedule')
            <x-athlete.page-tabs.row>
                <x-athlete.toolbar-navigator :previous-href="$this->previousScheduleUrl" :next-href="$this->nextScheduleUrl">
                    <flux:date-picker size="sm" locale="de-DE" wire:model.live="selectedScheduleDate" class="w-full" />
                </x-athlete.toolbar-navigator>
            </x-athlete.page-tabs.row>
        @endif
    </x-athlete.page-tabs>

    <div class="w-full md:mx-auto md:max-w-[900px] px-0 py-0">
        @if ($dashboardMode === 'train')
            <livewire:athlete.day-schedule :date="$this->selectedTrainDateValue" :show-readiness="true" />
        @elseif ($dashboardMode === 'schedule')
            <livewire:athlete.day-schedule :date="$this->selectedScheduleDateValue" view-mode="week" :show-readiness="false" />
        @elseif ($dashboardMode === 'unrecorded')
            <livewire:athlete.day-schedule :date="$dashboardDate" :show-readiness="false" schedule-filter="unrecorded" />
        @endif
    </div>
</div>
