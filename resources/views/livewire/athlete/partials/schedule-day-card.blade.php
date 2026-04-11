<x-athlete.section>
    <div>
        <div class="flex items-start justify-between gap-3 sm:items-center">
            <flux:heading size="lg">{{ $day['formattedDate'] }}</flux:heading>
            <div class="hidden sm:block">
                <flux:badge size="sm">{{ $day['sessionCount'] }} {{ Str::plural('session', $day['sessionCount']) }}</flux:badge>
            </div>
        </div>
        <div class="mt-2 sm:hidden">
            <flux:badge size="sm">{{ $day['sessionCount'] }} {{ Str::plural('session', $day['sessionCount']) }}</flux:badge>
        </div>
    </div>

    <div class="space-y-4">
        @if ($day['amPrograms']->count() > 0)
            <div class="space-y-2">
                <flux:badge color="amber" size="sm">AM</flux:badge>

                <div class="space-y-1">
                    @foreach ($day['amPrograms'] as $program)
                        <a
                            wire:key="{{ $day['date'] }}-am-{{ $program->slotId }}"
                            href="{{ route('athlete.programs.show', ['date' => $day['date'], 'trainingProgram' => $program->trainingProgramId, 'from' => request()->fullUrl()]) }}"
                            wire:navigate
                            class="flex items-stretch justify-between gap-0 overflow-hidden rounded-lg bg-zinc-50 transition hover:bg-zinc-100 dark:bg-zinc-800 dark:hover:bg-zinc-700/80"
                        >
                            @if ($program->categoryColor && $program->categoryShortName)
                                <div
                                    class="flex w-8 shrink-0 items-center justify-center self-stretch"
                                    style="background-color: {{ $program->categoryColor }}"
                                >
                                    <span
                                        class="text-[10px] font-semibold uppercase tracking-[0.18em] text-white"
                                        style="writing-mode: vertical-rl; transform: rotate(180deg);"
                                    >
                                        {{ $program->categoryShortName }}
                                    </span>
                                </div>
                            @endif
                            <div class="flex min-w-0 flex-1 items-start justify-between gap-3 px-3 py-2 sm:items-center">
                                <div class="min-w-0 flex-1">
                                    <span class="block text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400 sm:hidden">{{ $program->time }}</span>
                                    <div class="min-w-0 sm:flex sm:items-center sm:gap-2">
                                        <span class="hidden text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400 sm:inline">{{ $program->time }}</span>
                                        <span class="block text-sm font-medium text-zinc-900 dark:text-white sm:truncate">{{ $program->name }}</span>
                                    </div>
                                    <span class="mt-1 inline-flex rounded-md bg-zinc-200 px-2 py-1 text-[11px] font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200 sm:hidden">
                                        {{ $program->exerciseCount }} {{ Str::plural('exercise', $program->exerciseCount) }}
                                    </span>
                                </div>
                                <div class="flex shrink-0 items-center self-center gap-2">
                                    <span class="hidden rounded-md bg-zinc-200 px-2 py-1 text-[11px] font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200 sm:inline-flex">
                                        {{ $program->exerciseCount }} {{ Str::plural('exercise', $program->exerciseCount) }}
                                    </span>
                                    <flux:icon.chevron-right class="size-4 text-zinc-400 dark:text-zinc-500" />
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($day['pmPrograms']->count() > 0)
            <div class="space-y-2">
                <flux:badge color="blue" size="sm">PM</flux:badge>

                <div class="space-y-1">
                    @foreach ($day['pmPrograms'] as $program)
                        <a
                            wire:key="{{ $day['date'] }}-pm-{{ $program->slotId }}"
                            href="{{ route('athlete.programs.show', ['date' => $day['date'], 'trainingProgram' => $program->trainingProgramId, 'from' => request()->fullUrl()]) }}"
                            wire:navigate
                            class="flex items-stretch justify-between gap-0 overflow-hidden rounded-lg bg-zinc-50 transition hover:bg-zinc-100 dark:bg-zinc-800 dark:hover:bg-zinc-700/80"
                        >
                            @if ($program->categoryColor && $program->categoryShortName)
                                <div
                                    class="flex w-8 shrink-0 items-center justify-center self-stretch"
                                    style="background-color: {{ $program->categoryColor }}"
                                >
                                    <span
                                        class="text-[10px] font-semibold uppercase tracking-[0.18em] text-white"
                                        style="writing-mode: vertical-rl; transform: rotate(180deg);"
                                    >
                                        {{ $program->categoryShortName }}
                                    </span>
                                </div>
                            @endif
                            <div class="flex min-w-0 flex-1 items-start justify-between gap-3 px-3 py-2 sm:items-center">
                                <div class="min-w-0 flex-1">
                                    <span class="block text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400 sm:hidden">{{ $program->time }}</span>
                                    <div class="min-w-0 sm:flex sm:items-center sm:gap-2">
                                        <span class="hidden text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400 sm:inline">{{ $program->time }}</span>
                                        <span class="block text-sm font-medium text-zinc-900 dark:text-white sm:truncate">{{ $program->name }}</span>
                                    </div>
                                    <span class="mt-1 inline-flex rounded-md bg-zinc-200 px-2 py-1 text-[11px] font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200 sm:hidden">
                                        {{ $program->exerciseCount }} {{ Str::plural('exercise', $program->exerciseCount) }}
                                    </span>
                                </div>
                                <div class="flex shrink-0 items-center self-center gap-2">
                                    <span class="hidden rounded-md bg-zinc-200 px-2 py-1 text-[11px] font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200 sm:inline-flex">
                                        {{ $program->exerciseCount }} {{ Str::plural('exercise', $program->exerciseCount) }}
                                    </span>
                                    <flux:icon.chevron-right class="size-4 text-zinc-400 dark:text-zinc-500" />
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-athlete.section>
