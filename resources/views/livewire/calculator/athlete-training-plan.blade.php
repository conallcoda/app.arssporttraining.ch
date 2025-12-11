<div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <div
        class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-start gap-2 justify-between sm:items-center">
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
        <div class="border-b border-zinc-200 dark:border-zinc-700">
            <flux:tabs class="px-4">
                @foreach ($athletes as $athlete)
                    <flux:tab wire:click="selectAthlete({{ $athlete->id }})"
                        :variant="($selectedAthleteId === $athlete->id || ($selectedAthleteId === null && $loop->first)) ? 'active' : null"
                        class="cursor-pointer">
                        {{ $athlete->name }}
                    </flux:tab>
                @endforeach
            </flux:tabs>
        </div>

        @if ($this->selectedAthlete)
            <div class="p-4">
                @if (count($this->exerciseBlocks) > 0)
                    <div class="flex flex-wrap">
                        @foreach ($this->exerciseBlocks as $item)
                            <div class="p-4">
                            <x-exercise-block-grid :block="$item['block']" :title="$item['exercise']->name" :highlightedCells="[]" headingSize="lg">
                                <x-slot:titleAction>
                                    <button wire:click="openBreakdown({{ $item['exercise']->id }})"
                                        class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors"
                                        title="View step-by-step breakdown">
                                        <flux:icon.circle-question-mark class="size-4" />
                                    </button>
                                </x-slot:titleAction>
                                <x-slot:subtitleContent>
                                    <div class="flex items-center gap-1.5">
                                        @php
                                            $exerciseId = $item['exercise']->id;
                                            $targetValue = $this->getExerciseTarget($exerciseId);
                                            $repsValue = $this->getExerciseStartingReps($exerciseId);
                                            $setsValue = $this->getExerciseSets($exerciseId);
                                            $hasTargetOverride = isset($exerciseOverrides[$exerciseId]['target']);
                                            $hasRepsOverride = isset($exerciseOverrides[$exerciseId]['startingReps']);
                                            $hasSetsOverride = isset($exerciseOverrides[$exerciseId]['sets']);
                                        @endphp
                                        <div wire:key="target-{{ $exerciseId }}-{{ $targetValue }}"
                                            class="inline-flex items-center rounded-md border px-1.5 py-0.5 {{ $hasTargetOverride ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800' }}"
                                            x-data="editable_cell($wire, 'updateExerciseOverride', [{{ $exerciseId }}, 'target'], {{ $targetValue }})" @click="startEditing">
                                            <span
                                                class="{{ $hasTargetOverride ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500 dark:text-zinc-400' }} mr-1">Target:</span>
                                            <span x-show="!editing" class="font-medium cursor-pointer"
                                                x-text="value + '%'"></span>
                                            <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                                @blur="save" @keydown="handleKeydown" type="number" step="0.1"
                                                min="1"
                                                class="w-12 text-center bg-transparent border-none outline-none p-0 text-sm font-medium focus:ring-0" />
                                        </div>
                                        <div wire:key="reps-{{ $exerciseId }}-{{ $repsValue }}"
                                            class="inline-flex items-center rounded-md border px-1.5 py-0.5 {{ $hasRepsOverride ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800' }}"
                                            x-data="editable_cell($wire, 'updateExerciseOverride', [{{ $exerciseId }}, 'startingReps'], {{ $repsValue }})" @click="startEditing">
                                            <span
                                                class="{{ $hasRepsOverride ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500 dark:text-zinc-400' }} mr-1">Start
                                                Reps:</span>
                                            <span x-show="!editing" class="font-medium cursor-pointer"
                                                x-text="value"></span>
                                            <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                                @blur="save" @keydown="handleKeydown" type="number" step="1"
                                                min="1" max="20"
                                                class="w-8 text-center bg-transparent border-none outline-none p-0 text-sm font-medium focus:ring-0" />
                                        </div>
                                        <div wire:key="sets-{{ $exerciseId }}-{{ $setsValue }}"
                                            class="inline-flex items-center rounded-md border px-1.5 py-0.5 {{ $hasSetsOverride ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800' }}"
                                            x-data="editable_cell($wire, 'updateExerciseOverride', [{{ $exerciseId }}, 'sets'], {{ $setsValue }})" @click="startEditing">
                                            <span
                                                class="{{ $hasSetsOverride ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500 dark:text-zinc-400' }} mr-1">Sets:</span>
                                            <span x-show="!editing" class="font-medium cursor-pointer"
                                                x-text="value"></span>
                                            <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                                @blur="save" @keydown="handleKeydown" type="number" step="1"
                                                min="1" max="6"
                                                class="w-8 text-center bg-transparent border-none outline-none p-0 text-sm font-medium focus:ring-0" />
                                        </div>
                                    </div>
                                </x-slot:subtitleContent>
                            </x-exercise-block-grid>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center text-zinc-500 dark:text-zinc-400 py-8">
                        No exercises configured.
                    </div>
                @endif
            </div>
        @else
            <div class="p-4 text-center text-zinc-500 dark:text-zinc-400">
                Select an athlete to view their training plans.
            </div>
        @endif
    @else
        <div class="p-4 text-center text-zinc-500 dark:text-zinc-400">
            No athletes available.
        </div>
    @endif

    <x-center-modal name="breakdown-modal" wire:model="showBreakdownModal" :title="$this->breakdownHistory?->exercise()->name . ' Training Plan'" :subtitle="'Step-by-step progression for ' . $this->selectedAthlete?->name">
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
