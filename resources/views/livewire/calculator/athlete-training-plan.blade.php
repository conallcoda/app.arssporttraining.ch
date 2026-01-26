<div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <div
        class="sticky top-0 z-20 px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-start gap-2 justify-between sm:items-center">
        <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
            Athlete Training Plans
        </h3>
        <x-help-tooltip>
            <p>View the final training blocks for all exercises for each athlete. Click the ? icon to see how the the
                plans are calculated step-by-step.</p>
            <div class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700 space-y-1.5">
                <div class="flex items-center gap-2">
                    <span
                        class="size-4 rounded bg-blue-200 dark:bg-blue-900/20 border border-zinc-300 dark:border-zinc-600"></span>
                    <span>Reps</span>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="size-4 rounded bg-green-200 dark:bg-green-900/20 border border-zinc-300 dark:border-zinc-600"></span>
                    <span>Weight</span>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="size-4 rounded bg-orange-200 dark:bg-orange-900/20 border border-zinc-300 dark:border-zinc-600"></span>
                    <span>Projected 1RM</span>
                </div>
            </div>
        </x-help-tooltip>
    </div>

    @if (count($athletes) > 0)
        <div class="flex">
            <div class="w-64 border-r border-zinc-200 dark:border-zinc-700 flex-shrink-0 sticky top-[3.75rem] self-start max-h-[calc(100vh-4rem)] overflow-y-auto">
                <div class="sticky top-0 bg-zinc-50 dark:bg-zinc-800 px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                    <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">Athletes</flux:heading>
                </div>
                <div class="p-2">
                    @foreach ($athletes as $athlete)
                        <button
                            wire:click="selectAthlete({{ $athlete->id }})"
                            class="w-full text-left px-3 py-2 rounded-lg transition-colors {{ ($selectedAthleteId === $athlete->id || ($selectedAthleteId === null && $loop->first)) ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-900 dark:text-zinc-100' }}"
                        >
                            <div class="font-medium text-sm">{{ $athlete->name() }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ number_format($this->getAthleteOneRepMax($athlete), 1) }}kg
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="flex-1">
                @if ($this->selectedAthlete)
                    <div>
                        <flux:heading size="xl" class="p-6 pb-3">
                            Training Plan for {{ $this->selectedAthlete->name() }}
                        </flux:heading>
                        @if (count($this->groupedExerciseBlocks) > 0)
                            @foreach ($this->groupedExerciseBlocks as $groupName => $groupExercises)
                                <div class="border-b border-zinc-200 dark:border-zinc-700 last:border-b-0">
                                    <div class="px-6 py-3 bg-zinc-100 dark:bg-zinc-800">
                                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 font-semibold">
                                            {{ $groupName }}
                                        </flux:heading>
                                    </div>
                                    <div class="flex flex-wrap">
                                        @foreach ($groupExercises as $item)
                                            <div class="p-6 {{ !$loop->last ? 'border-r border-zinc-200 dark:border-zinc-700' : '' }}">
                                                @php
                                                    $exerciseId = $item['exercise']->id;
                                                    $targetValue = $this->getExerciseTarget($exerciseId);
                                                    $repsValue = $this->getExerciseStartingReps($exerciseId);
                                                    $setsValue = $this->getExerciseSets($exerciseId);
                                                    $hasTargetOverride = isset($exerciseOverrides[$exerciseId]['target']);
                                                    $hasRepsOverride = isset($exerciseOverrides[$exerciseId]['startingReps']);
                                                    $hasSetsOverride = isset($exerciseOverrides[$exerciseId]['sets']);
                                                @endphp
                                                <x-exercise-block-grid :block="$item['block']" :title="$item['exercise']->name" :highlightedCells="[]"
                                                    headingSize="lg" :overrideDisplayMode="$overrideDisplayMode" :exerciseId="$exerciseId" :targetValue="$targetValue"
                                                    :targetDefault="$this->config?->target ?? 0"
                                                    :repsValue="$repsValue" :repsDefault="$this->config?->strategyConfig['set_paired_reps']['startingReps'] ?? 10"
                                                    :setsValue="$setsValue" :setsDefault="$this->config?->strategyConfig['create_empty_block']['sets'] ?? 4"
                                                    :hasTargetOverride="$hasTargetOverride" :hasRepsOverride="$hasRepsOverride"
                                                    :hasSetsOverride="$hasSetsOverride">
                                                    <x-slot:titleAction>
                                                        <button wire:click="openBreakdown({{ $this->selectedAthlete->id }}, {{ $item['exercise']->id }})"
                                                            class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors"
                                                            title="View step-by-step breakdown">
                                                            <flux:icon.circle-question-mark class="size-4" />
                                                        </button>
                                                    </x-slot:titleAction>
                                                </x-exercise-block-grid>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center text-zinc-500 dark:text-zinc-400 p-8">
                                No exercises configured.
                            </div>
                        @endif
                    </div>
                @else
                    <div class="p-6 text-center text-zinc-500 dark:text-zinc-400">
                        Select an athlete to view their training plan.
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="p-4 text-center text-zinc-500 dark:text-zinc-400">
            No athletes available.
        </div>
    @endif

    <x-center-modal name="breakdown-modal" wire:model="showBreakdownModal" :title="$this->breakdownHistory?->exercise()->name . ' Training Plan'" :subtitle="'Step-by-step progression for ' . $this->breakdownAthlete?->name()">
        @if ($this->breakdownHistory)
            <div class="overflow-x-auto">
                <div class="flex flex-wrap gap-4">
                    @foreach ($this->breakdownHistory->results as $index => $result)
                        <x-exercise-block-grid :block="$result->current" :title="'Step ' . ($index + 1) . ': ' . $result->title()" :helpText="$result->helpText()"
                            :highlightedCells="$result->getHighlightedCells()" />
                    @endforeach
                </div>
            </div>
        @endif
    </x-center-modal>
</div>
