<div>
    <x-athlete.page-tabs active="record">
        <x-athlete.page-tabs.row>
            <flux:tabs variant="segmented" class="w-full [&>[data-flux-tabs]]:grid [&>[data-flux-tabs]]:w-full [&>[data-flux-tabs]]:grid-cols-2">
                <flux:tab
                    name="today"
                    icon="calendar-1"
                    href="{{ route('athlete.dashboard', ['trainView' => 'today']) }}"
                    wire:navigate
                    :selected="$trainView === 'today'"
                >
                    Today
                </flux:tab>
                <flux:tab
                    name="unrecorded"
                    icon="list"
                    href="{{ route('athlete.dashboard', ['trainView' => 'unrecorded']) }}"
                    wire:navigate
                    :selected="$trainView === 'unrecorded'"
                >
                    Unrecorded
                </flux:tab>
            </flux:tabs>
        </x-athlete.page-tabs.row>
    </x-athlete.page-tabs>

    <div class="mx-auto max-w-2xl px-2 py-0 sm:px-4 sm:py-0">
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
