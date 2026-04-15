@php
    $progressSegments = $this->progressSegments;
    $exerciseCategory = $trainingProgram->program->exerciseCategory;
    $programCategoryLabel = $exerciseCategory?->name ?: $exerciseCategory?->short_name;
@endphp
<div>
    <div
        class="sticky top-0 z-20 border-b border-zinc-200 bg-zinc-100/95 backdrop-blur dark:border-zinc-700 dark:bg-zinc-800/95">
        <div class="athlete-toolbar__row w-full">
            <a href="{{ $this->backUrl }}" wire:navigate
                @if ($this->from) onclick="if (window.history.length > 1) { event.preventDefault(); window.history.back(); }" @endif
                class="inline-flex min-h-11 items-center gap-3 bg-zinc-800/5 px-4 py-3 text-sm text-zinc-700 transition hover:bg-white dark:bg-white/10 dark:text-white dark:hover:bg-white/20"
                aria-label="{{ $this->backLabel }}">
                <flux:icon.chevron-left class="size-4 shrink-0" />
                <span class="truncate font-medium">{{ $this->backLabel }}</span>
            </a>
        </div>

        <div
            class="flex w-full items-center gap-3 border-t border-zinc-200 bg-zinc-50 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900/40">
            <x-athlete.category-badge class="shrink-0" :label="$programCategoryLabel" :color="$exerciseCategory?->color" />

            <div class="min-w-0 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                <span class="truncate">
                    {{ \Carbon\CarbonImmutable::parse($date)->locale(app()->getLocale())->translatedFormat('D, d.m.Y') }}
                    •
                    {{ $this->currentSlot->datetime->format('gA') }}
                </span>
            </div>
        </div>

        <div class="border-t border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900/40">
            <x-athlete.recorded-progress :segments="$progressSegments" label-align="center" />
        </div>
    </div>

    <div class="mx-auto max-w-2xl px-2 py-0 sm:px-4 sm:py-0">
        <div class="space-y-0">
            @forelse ($programExercises as $exercise)
                <x-athlete.section :row="$loop->iteration" class="py-0" content-class="space-y-0 py-3 sm:py-5"
                    wire:key="exercise-{{ $exercise['id'] }}" x-data="{ open: false }">
                    <div x-on:click="open = !open" x-on:keydown.enter.prevent="open = !open"
                        x-on:keydown.space.prevent="open = !open" :aria-expanded="open" role="button" tabindex="0"
                        class="flex w-full cursor-pointer items-start justify-between gap-3">
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <div class="text-sm font-medium leading-tight text-zinc-800 dark:text-white sm:text-lg">
                                {{ $exercise['groupLabel'] ? $exercise['groupLabel'] . ' - ' . $exercise['name'] : $exercise['index'] . '. ' . $exercise['name'] }}
                            </div>

                            <span
                                class="status-badge inline-flex rounded-md px-2 py-1 text-[10px] font-medium sm:text-xs"
                                style="--status-bar-light: {{ $exercise['statusColor']['light'] }}; --status-bar-dark: {{ $exercise['statusColor']['dark'] }};">
                                {{ $exercise['statusLabel'] }}
                            </span>
                        </div>
                        <flux:icon.chevron-up x-show="open" x-cloak
                            class="mt-1 size-5 shrink-0 text-zinc-500 dark:text-zinc-400" />
                        <flux:icon.chevron-down x-show="!open" x-cloak
                            class="mt-1 size-5 shrink-0 text-zinc-500 dark:text-zinc-400" />
                    </div>

                    <div x-show="open" x-cloak x-collapse x-bind:class="open ? 'pt-3' : 'pt-0'" class="space-y-3 sm:space-y-5">
                        <div class="space-y-2">
                            @if (!empty($exercise['equipmentBadges']) || !empty($exercise['modifierBadges']))
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($exercise['equipmentBadges'] as $badge)
                                        <flux:badge color="blue" size="sm" class="text-[10px] sm:text-xs">
                                            {{ $badge }}</flux:badge>
                                    @endforeach

                                    @foreach ($exercise['modifierBadges'] as $badge)
                                        <flux:badge size="sm" class="text-[10px] sm:text-xs">{{ $badge }}
                                        </flux:badge>
                                    @endforeach
                                </div>
                            @endif

                            @if ($exercise['weekDetails'])
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($exercise['weekDetails'] as $detail)
                                        <span
                                            class="inline-flex rounded-md bg-zinc-200 px-2 py-1 text-[10px] font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                                            {{ $detail }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($exercise['notes'])
                                <div class="space-y-2">
                                    @foreach ($exercise['notes'] as $note)
                                        <div class="rounded-lg bg-zinc-50 px-2.5 py-2 dark:bg-zinc-800">
                                            <div
                                                class="text-[10px] font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                {{ $note['label'] }}
                                            </div>
                                            <div
                                                class="mt-1 whitespace-pre-line text-xs text-zinc-700 dark:text-zinc-200">
                                                {{ $note['value'] }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($exercise['instructions'])
                                <flux:text
                                    class="whitespace-pre-line text-[11px] text-zinc-600 dark:text-zinc-300 sm:text-sm">
                                    {{ $exercise['instructions'] }}
                                </flux:text>
                            @endif

                            @if ($exercise['videoUrl'] || !empty($exercise['photoUrls']))
                                <div class="flex items-center gap-3">
                                    @if ($exercise['videoUrl'])
                                        <button type="button"
                                            x-on:click="$dispatch('open-youtube-player', { url: @js($exercise['videoUrl']) })"
                                            class="inline-flex size-9 items-center justify-center rounded-md bg-zinc-200 text-zinc-700 transition hover:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-600"
                                            aria-label="Watch video">
                                            <flux:icon.play-circle class="size-5" />
                                        </button>
                                    @endif

                                    @if (!empty($exercise['photoUrls']))
                                        <button type="button"
                                            x-on:click="$dispatch('open-exercise-gallery', { images: @js($exercise['photoUrls']), index: 0 })"
                                            class="inline-flex size-9 items-center justify-center rounded-md bg-zinc-200 text-zinc-700 transition hover:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-600"
                                            aria-label="View gallery">
                                            <flux:icon.camera class="size-5" />
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if ($exercise['sessionRows'])
                            <div>
                                <div class="overflow-x-auto border border-zinc-300 dark:border-zinc-600">
                                    <table class="min-w-full border-collapse text-xs">
                                        <thead>
                                            <tr class="bg-zinc-100 dark:bg-zinc-800">
                                                <th
                                                    class="sticky left-0 z-10 border-b border-r border-zinc-300 bg-zinc-100 px-2 py-1.5 text-center font-semibold dark:border-zinc-600 dark:bg-zinc-800 whitespace-nowrap">
                                                    {{ $exercise['setLabel'] }}
                                                </th>
                                                @foreach ($exercise['sessionRows'] as $row)
                                                    <th
                                                        class="border-b border-r border-zinc-300 px-2 py-1.5 text-center font-semibold last:border-r-0 dark:border-zinc-600 whitespace-nowrap {{ $row['labelClass'] }}">
                                                        {{ $row['label'] }}
                                                    </th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @for ($set = 0; $set < $exercise['setCount']; $set++)
                                                <tr>
                                                    <td
                                                        class="sticky left-0 z-10 border-r border-t border-zinc-300 bg-zinc-100 px-2 py-1.5 text-center font-medium dark:border-zinc-600 dark:bg-zinc-800 whitespace-nowrap">
                                                        {{ $set + 1 }}
                                                    </td>
                                                    @foreach ($exercise['sessionRows'] as $row)
                                                        <td
                                                            class="border-r border-t border-zinc-300 px-2 py-1.5 text-center last:border-r-0 dark:border-zinc-600 whitespace-nowrap {{ $row['valueClasses'][$set] ?? '' }}">
                                                            {{ $row['values'][$set] ?? '—' }}
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endfor
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        <div class="flex flex-wrap justify-end gap-2">
                            <flux:button size="sm" variant="primary"
                                wire:click="markExerciseCompleted({{ $exercise['id'] }})" x-on:click="open = false">
                                Mark Done
                            </flux:button>
                            <flux:button size="sm" variant="ghost"
                                wire:click="markExerciseSkipped({{ $exercise['id'] }})" x-on:click="open = false">
                                Skip
                            </flux:button>
                        </div>
                    </div>
                </x-athlete.section>
            @empty
                <x-athlete.section mobile-shade="none">
                    <flux:text>No exercises are available for this program.</flux:text>
                </x-athlete.section>
            @endforelse
        </div>
    </div>
</div>
