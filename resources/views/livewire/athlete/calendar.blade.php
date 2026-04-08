<div>
    <div class="mx-auto max-w-2xl px-2 py-6 sm:px-4 sm:py-8">
        <x-athlete.page-tabs active="calendar" />

        <flux:tabs class="mb-6">
            <flux:tab
                name="day"
                href="{{ route('athlete.dashboard.calendar') }}"
                wire:navigate
                :selected="$calendarView === 'day'"
            >
                Day
            </flux:tab>
            <flux:tab
                name="week"
                href="{{ route('athlete.dashboard.calendar.week') }}"
                wire:navigate
                :selected="$calendarView === 'week'"
            >
                Week
            </flux:tab>
        </flux:tabs>

        @if ($calendarView === 'day')
            <livewire:athlete.day-schedule :date="now()->format('Y-m-d')" :show-readiness="false" />
        @elseif ($calendarView === 'week')
            <div class="py-12 text-center">
                <flux:icon.calendar class="mx-auto size-8 text-zinc-600 dark:text-zinc-400" />
                <flux:heading size="lg" class="mt-3">Coming soon</flux:heading>
                <flux:text class="mt-1">Weekly view will be available soon.</flux:text>
            </div>
        @endif
    </div>
</div>
