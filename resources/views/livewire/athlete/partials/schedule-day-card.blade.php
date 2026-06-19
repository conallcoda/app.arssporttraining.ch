@php
    $totalSessions = $day['programs']->count();
    $fullyRecordedSessions = $day['programs']->filter(
        fn ($program) => $program->totalExerciseCount > 0 && $program->recordedExerciseCount >= $program->totalExerciseCount
    )->count();
    $showRecordingState = $totalSessions > 0 && $fullyRecordedSessions === $totalSessions;

    if ($totalSessions > 0 && $fullyRecordedSessions === $totalSessions) {
        $dayStatusLabel = 'Recorded';
        $dayStatusColor = ['light' => '110 231 183', 'dark' => '52 211 153'];
    } else {
        $dayStatusLabel = 'Pending';
        $dayStatusColor = ['light' => '228 228 231', 'dark' => '161 161 170'];
    }
@endphp

<x-athlete.section :row="$row ?? null" x-data="{ open: false }" class="py-0" content-class="py-3">
    <button
        type="button"
        x-on:click="open = !open"
        :aria-expanded="open"
        class="flex w-full items-center justify-between gap-3 text-left text-base font-normal text-zinc-800 dark:text-white"
    >
        <div class="flex min-w-0 items-center gap-2">
            <span class="truncate">{{ $day['formattedDate'] }}</span>
            @if ($showRecordingState)
                <span
                    class="status-badge inline-flex shrink-0 rounded-md px-2 py-1 text-[10px] font-medium"
                    style="--status-bar-light: {{ $dayStatusColor['light'] }}; --status-bar-dark: {{ $dayStatusColor['dark'] }};"
                >
                    {{ $dayStatusLabel }}
                </span>
            @endif
        </div>
        <flux:icon.chevron-up x-show="open" x-cloak class="size-5 shrink-0 text-zinc-500 dark:text-zinc-400" />
        <flux:icon.chevron-down x-show="!open" x-cloak class="size-5 shrink-0 text-zinc-500 dark:text-zinc-400" />
    </button>

    @if ($day['programs']->count() > 0)
        <div x-show="open" x-cloak x-collapse class="space-y-3 pt-3">
            @foreach ($day['programs'] as $program)
                @php
                    $cardClasses = 'flex items-start justify-between gap-0 overflow-hidden rounded-lg bg-zinc-50 transition hover:bg-zinc-100 dark:bg-zinc-800 dark:hover:bg-zinc-700/80';
                @endphp
                @if ($program->previewKey)
                    <button
                        type="button"
                        wire:key="{{ $day['date'] }}-{{ $program->slotId }}"
                        wire:click="openPreviewSession('{{ $program->previewKey }}')"
                        class="{{ $cardClasses }} w-full text-left"
                    >
                @else
                    <a
                        wire:key="{{ $day['date'] }}-{{ $program->slotId }}"
                        href="{{ route('athlete.programs.show', ['date' => $day['date'], 'trainingProgram' => $program->trainingProgramId, 'from' => request()->fullUrl()]) }}"
                        wire:navigate
                        class="{{ $cardClasses }}"
                    >
                @endif
                    <div class="flex w-14 shrink-0 items-start justify-center px-2 pt-3">
                        <div class="rounded px-2 py-0.5 text-sm font-semibold uppercase tracking-wide text-white {{ $program->period === 'pm' ? 'bg-blue-500 dark:bg-blue-600' : 'bg-amber-400 dark:bg-amber-500' }}">
                            {{ $program->periodLabel ?? strtoupper($program->period) }}
                        </div>
                    </div>
                    <div class="flex min-w-0 flex-1 items-start justify-between gap-3 px-3 py-2">
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="space-y-1">
                                <div class="text-xs font-medium text-zinc-700 dark:text-zinc-200">
                                    {{ $program->time }}
                                </div>
                                <x-athlete.category-badge :label="$program->categoryName" :color="$program->categoryColor" />
                            </div>
                            @if ($program->recordedExerciseCount > 0)
                                <x-athlete.recorded-progress :segments="$program->progressSegments" />
                            @endif
                        </div>
                        <flux:icon.chevron-right class="mt-1 size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                    </div>
                @if ($program->previewKey)
                    </button>
                @else
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</x-athlete.section>
