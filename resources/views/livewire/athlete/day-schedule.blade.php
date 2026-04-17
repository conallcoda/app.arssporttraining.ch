<div>
    @if ($hasSchedule)
        <div class="space-y-0">
            @if ($showReadiness)
                @if ($readinessScore === null)
                    <div class="px-3 py-3">
                        <flux:callout icon="triangle-alert" color="amber">
                            Please fill in the readiness survey before starting your training.
                        </flux:callout>
                    </div>
                @endif

                <x-athlete.section>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <flux:icon.activity class="size-5 text-zinc-600 dark:text-zinc-400" />
                            <flux:heading size="lg">Readiness</flux:heading>
                        </div>

                        <x-athlete.readiness-gauge :score="$readinessScore" :label="$readinessLabel" :color="$readinessColor" :editable="true" />
                    </div>
                </x-athlete.section>
            @endif

            @if ($canShowTraining)
                @foreach ($days as $day)
                    @include('livewire.athlete.partials.schedule-day-card', ['day' => $day, 'row' => $loop->iteration])
                @endforeach
            @endif
        </div>

    @else
        <div class="py-12 text-center">
            <flux:icon.calendar class="mx-auto size-8 text-zinc-600 dark:text-zinc-400" />
            <flux:heading size="lg" class="mt-3">Nothing scheduled</flux:heading>
            <flux:text class="mt-1">{{ $emptyStateMessage }}</flux:text>
        </div>
    @endif
</div>
