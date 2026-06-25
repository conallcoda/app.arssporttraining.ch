<div>
    @if ($this->selectedSession)
        @php
            $selectedSession = $this->selectedSession;
        @endphp

        <x-athlete.program-session-detail
            :embedded="true"
            back-mode="action"
            back-action="backToPreviewList"
            back-label="Back to Preview"
            :date-label="$selectedSession['formattedDate']"
            :time-label="$selectedSession['timeLabel']"
            :category-label="$selectedSession['categoryLabel']"
            :category-color="$selectedSession['categoryColor']"
            :show-progress="! $selectedSession['isFutureSession']"
            :progress-segments="$selectedSession['progressSegments']"
            :shows-section-tabs="count($selectedSession['sectionTabs']) > 1"
            :section-tabs="$selectedSession['sectionTabs']"
            section-model="activeSection"
            :section-instructions="$this->sectionInstructions"
            :program-exercises="$selectedSession['exercisesBySection'][$activeSection] ?? []"
            :is-future-session="$selectedSession['isFutureSession']"
            :athlete-edits-enabled="false"
        />
    @else
        <div class="space-y-0">
            @forelse ($this->days as $day)
                @include('livewire.athlete.partials.schedule-day-card', ['day' => $day, 'row' => $loop->iteration])
            @empty
                <div class="py-12 text-center">
                    <flux:icon.calendar class="mx-auto size-8 text-zinc-600 dark:text-zinc-400" />
                    <flux:heading size="lg" class="mt-3">Nothing scheduled</flux:heading>
                    <flux:text class="mt-1">No sessions are available for this athlete preview.</flux:text>
                </div>
            @endforelse
        </div>
    @endif
</div>
