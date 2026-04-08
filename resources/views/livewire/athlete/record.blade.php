<div>
    <div class="mx-auto max-w-2xl px-2 py-6 sm:px-4 sm:py-8">
        <x-athlete.page-tabs active="record" />

        <livewire:athlete.day-schedule :date="now()->format('Y-m-d')" :show-readiness="true" :readiness-score="$readinessScore" :readiness-label="$readinessLabel" :readiness-color="$readinessColor" />
    </div>
</div>
