<div>
    <x-athlete.page-tabs active="record">
        <x-athlete.page-tabs.row>
            <x-athlete.segmented-tabs :tabs="[
                [
                    'name' => 'today',
                    'label' => 'Today',
                    'icon' => 'calendar-1',
                    'href' => route('athlete.dashboard', ['trainView' => 'today']),
                    'navigate' => true,
                    'selected' => $trainView === 'today',
                ],
                [
                    'name' => 'unrecorded',
                    'label' => 'Unrecorded',
                    'icon' => 'list',
                    'href' => route('athlete.dashboard', ['trainView' => 'unrecorded']),
                    'navigate' => true,
                    'selected' => $trainView === 'unrecorded',
                ],
            ]" />
        </x-athlete.page-tabs.row>
    </x-athlete.page-tabs>

    <div class="w-full md:mx-auto md:max-w-[900px] px-0 py-0">
        <livewire:athlete.day-schedule
            :date="$dashboardDate"
            :show-readiness="$trainView === 'today'"
            :readiness-score="$readinessScore"
            :readiness-label="$readinessLabel"
            :readiness-color="$readinessColor"
            :schedule-filter="$trainView"
        />
    </div>
</div>
