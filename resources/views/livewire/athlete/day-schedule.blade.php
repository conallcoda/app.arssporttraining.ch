<div>
    @if ($hasSchedule)
        <div class="space-y-4">
            @if ($showReadiness)
                <flux:card class="shadow-sm">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <flux:icon.activity class="size-5 text-zinc-600 dark:text-zinc-400" />
                            <flux:heading size="lg">Readiness</flux:heading>
                        </div>

                        <x-athlete.readiness-gauge :score="$readinessScore" :label="$readinessLabel" :color="$readinessColor" :editable="true" />
                    </div>
                </flux:card>

                @if ($readinessScore === null)
                    <flux:callout icon="triangle-alert" color="amber">
                        Please fill in the readiness survey before recording your performance.
                    </flux:callout>
                @endif
            @endif

            @if (! $showReadiness || $readinessScore !== null)
                <flux:card class="shadow-sm">
                    <div class="mb-4 flex items-center gap-3">
                        <flux:icon.dumbbell class="size-5 text-zinc-600 dark:text-zinc-400" />
                        <flux:heading size="lg">Training</flux:heading>
                        <flux:badge size="sm">{{ count($amPrograms) + count($pmPrograms) }}</flux:badge>
                    </div>

                    <div class="space-y-4">
                        @if (count($amPrograms) > 0)
                            <div class="space-y-2">
                                <flux:badge color="amber" size="sm">AM</flux:badge>

                                <div class="space-y-1">
                                    @foreach ($amPrograms as $program)
                                        <div wire:key="am-{{ $program->id }}" class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800">
                                            <div class="flex items-center gap-2">
                                                @if ($program->categoryColor)
                                                    <span class="size-2.5 rounded-full" style="background-color: {{ $program->categoryColor }}"></span>
                                                @endif
                                                <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $program->name }}</span>
                                                @if ($program->categoryName)
                                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $program->categoryName }}</span>
                                                @endif
                                            </div>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $program->exerciseCount }} {{ Str::plural('exercise', $program->exerciseCount) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if (count($pmPrograms) > 0)
                            <div class="space-y-2">
                                <flux:badge color="blue" size="sm">PM</flux:badge>

                                <div class="space-y-1">
                                    @foreach ($pmPrograms as $program)
                                        <div wire:key="pm-{{ $program->id }}" class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800">
                                            <div class="flex items-center gap-2">
                                                @if ($program->categoryColor)
                                                    <span class="size-2.5 rounded-full" style="background-color: {{ $program->categoryColor }}"></span>
                                                @endif
                                                <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $program->name }}</span>
                                                @if ($program->categoryName)
                                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $program->categoryName }}</span>
                                                @endif
                                            </div>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $program->exerciseCount }} {{ Str::plural('exercise', $program->exerciseCount) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </flux:card>
            @endif
        </div>

    @else
        <div class="py-12 text-center">
            <flux:icon.calendar class="mx-auto size-8 text-zinc-600 dark:text-zinc-400" />
            <flux:heading size="lg" class="mt-3">Nothing scheduled</flux:heading>
            <flux:text class="mt-1">No training programs for this day.</flux:text>
        </div>
    @endif
</div>
