<div>
    <div class="athlete-toolbar sticky top-0 z-20 border-b border-zinc-200 bg-zinc-100/95 px-0 py-0 backdrop-blur dark:border-zinc-700 dark:bg-zinc-800/95 sm:static sm:border-0 sm:bg-transparent sm:px-0 sm:py-0 sm:backdrop-blur-none">
        <div class="sm:mx-auto sm:max-w-2xl sm:px-4">
            <div class="athlete-toolbar__row w-full">
                <a
                    href="{{ $this->backUrl }}"
                    wire:navigate
                    @if ($this->from)
                        onclick="if (window.history.length > 1) { event.preventDefault(); window.history.back(); }"
                    @endif
                    class="inline-flex min-h-11 items-center gap-3 bg-zinc-800/5 px-4 py-3 text-zinc-700 transition hover:bg-white sm:rounded-xl dark:bg-white/10 dark:text-white dark:hover:bg-white/20"
                    aria-label="{{ $this->backLabel }}"
                >
                    <flux:icon.chevron-left class="size-4 shrink-0" />
                    <span class="truncate text-base font-medium">{{ $this->backLabel }}</span>
                </a>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-2xl px-2 py-6 sm:px-4 sm:py-8">
        <x-athlete.section mobile-shade="none">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <flux:heading size="lg" class="text-2xl sm:text-2xl">{{ $trainingProgram->program->name }}</flux:heading>
                    <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400 sm:text-[0.9375rem]">
                        {{ \Carbon\CarbonImmutable::parse($date)->locale(app()->getLocale())->translatedFormat('l, d.m.Y') }}
                        •
                        {{ $this->currentSlot->datetime->format('H:i') }}
                    </flux:text>

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        @if ($trainingProgram->program->exerciseCategory?->name)
                            @php($programCategoryStyle = $this->categoryBadgeStyle($trainingProgram->program->exerciseCategory?->color))
                            <span
                                class="{{ $programCategoryStyle ? 'inline-flex rounded-md px-2 py-1 text-[11px] font-medium sm:text-sm' : 'inline-flex rounded-md bg-zinc-200 px-2 py-1 text-[11px] font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200 sm:text-sm' }}"
                                @if ($programCategoryStyle) style="{{ $programCategoryStyle }}" @endif
                            >
                                {{ $trainingProgram->program->exerciseCategory->name }}
                            </span>
                        @endif

                        <flux:badge size="sm" class="text-[11px] sm:text-sm">
                            {{ count($programExercises) }}
                            {{ Str::plural('exercise', count($programExercises)) }}
                        </flux:badge>

                        <span class="inline-flex rounded-md px-2 py-1 text-[11px] font-medium sm:text-sm {{ $this->slotStatusClass() }}">
                            {{ $this->slotStatusLabel() }}
                        </span>
                    </div>
                </div>
            </div>
        </x-athlete.section>

        <div class="mt-4 space-y-4">
            @forelse ($programExercises as $exercise)
                <x-athlete.section :mobile-shade="$loop->odd ? 'alt' : 'base'">
                    <div class="min-w-0 space-y-3">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading size="sm" class="text-sm leading-tight sm:text-lg">
                                    {{ $exercise['index'] }}. {{ $exercise['name'] }}
                                </flux:heading>

                                <span class="inline-flex rounded-md px-2 py-1 text-[10px] font-medium sm:text-xs {{ $exercise['statusClass'] }}">
                                    {{ $exercise['statusLabel'] }}
                                </span>

                                @if ($exercise['category'])
                                    @php($exerciseCategoryStyle = $this->categoryBadgeStyle($exercise['categoryColor']))
                                    <span
                                        class="{{ $exerciseCategoryStyle ? 'inline-flex rounded-md px-2 py-1 text-[10px] font-medium sm:text-xs' : 'inline-flex rounded-md bg-zinc-200 px-2 py-1 text-[10px] font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200 sm:text-xs' }}"
                                        @if ($exerciseCategoryStyle) style="{{ $exerciseCategoryStyle }}" @endif
                                    >
                                        {{ $exercise['category'] }}
                                    </span>
                                @endif
                            </div>

                            @if (! empty($exercise['equipmentBadges']) || ! empty($exercise['modifierBadges']))
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($exercise['equipmentBadges'] as $badge)
                                        <flux:badge color="blue" size="sm" class="text-[10px] sm:text-xs">{{ $badge }}</flux:badge>
                                    @endforeach

                                    @foreach ($exercise['modifierBadges'] as $badge)
                                        <flux:badge size="sm" class="text-[10px] sm:text-xs">{{ $badge }}</flux:badge>
                                    @endforeach
                                </div>
                            @endif

                            @if ($exercise['weekDetails'])
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($exercise['weekDetails'] as $detail)
                                        <span class="inline-flex rounded-md bg-zinc-200 px-2 py-1 text-[10px] font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                                            {{ $detail }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($exercise['notes'])
                                <div class="space-y-2">
                                    @foreach ($exercise['notes'] as $note)
                                        <div class="rounded-lg bg-zinc-50 px-2.5 py-2 dark:bg-zinc-800">
                                            <div class="text-[10px] font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                {{ $note['label'] }}
                                            </div>
                                            <div class="mt-1 whitespace-pre-line text-xs text-zinc-700 dark:text-zinc-200">
                                                {{ $note['value'] }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($exercise['instructions'])
                                <flux:text class="whitespace-pre-line text-[11px] text-zinc-600 dark:text-zinc-300 sm:text-sm">
                                    {{ $exercise['instructions'] }}
                                </flux:text>
                            @endif

                            @if ($exercise['videoUrl'])
                                <div>
                                    <a
                                        href="{{ $exercise['videoUrl'] }}"
                                        x-on:click.prevent="$dispatch('open-youtube-player', { url: @js($exercise['videoUrl']) })"
                                        class="inline-flex items-center gap-1 text-[11px] font-medium text-zinc-700 underline decoration-zinc-300 underline-offset-4 transition hover:text-zinc-900 dark:text-zinc-200 dark:decoration-zinc-600 dark:hover:text-white sm:text-sm"
                                    >
                                        <flux:icon.play class="size-3.5" />
                                        Watch video
                                    </a>
                                </div>
                            @endif

                            @if (! empty($exercise['photoUrls']))
                                <div>
                                    <a
                                        href="{{ $exercise['photoUrls'][0] }}"
                                        x-on:click.prevent="$dispatch('open-exercise-gallery', { images: @js($exercise['photoUrls']), index: 0 })"
                                        class="inline-flex items-center gap-1 text-[11px] font-medium text-zinc-700 underline decoration-zinc-300 underline-offset-4 transition hover:text-zinc-900 dark:text-zinc-200 dark:decoration-zinc-600 dark:hover:text-white sm:text-sm"
                                    >
                                        <flux:icon.photo class="size-3.5" />
                                        View Gallery
                                    </a>
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <flux:button size="sm" variant="primary" wire:click="markExerciseCompleted({{ $exercise['id'] }})">
                                Mark Done
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="markExerciseSkipped({{ $exercise['id'] }})">
                                Skip
                            </flux:button>
                        </div>
                    </div>

                    @if ($exercise['sessionRows'])
                        <div>
                            <div class="overflow-x-auto border border-zinc-300 dark:border-zinc-600">
                            <table class="min-w-full border-collapse text-xs">
                                <thead>
                                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                                        <th class="sticky left-0 z-10 border-b border-r border-zinc-300 bg-zinc-100 px-2 py-1.5 text-left font-semibold dark:border-zinc-600 dark:bg-zinc-800 whitespace-nowrap">
                                            {{ $exercise['setLabel'] }}
                                        </th>
                                        @for ($set = 0; $set < $exercise['setCount']; $set++)
                                            <th class="border-b border-r border-zinc-300 px-2 py-1.5 text-center font-semibold last:border-r-0 dark:border-zinc-600 whitespace-nowrap">
                                                {{ $set + 1 }}
                                            </th>
                                        @endfor
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($exercise['sessionRows'] as $row)
                                        <tr>
                                            <td class="sticky left-0 z-10 border-r border-t border-zinc-300 px-2 py-1.5 font-medium dark:border-zinc-600 whitespace-nowrap {{ $row['labelClass'] }}">
                                                {{ $row['label'] }}
                                            </td>
                                            @foreach ($row['values'] as $index => $value)
                                                <td class="border-r border-t border-zinc-300 px-2 py-1.5 text-center last:border-r-0 dark:border-zinc-600 whitespace-nowrap {{ $row['valueClasses'][$index] }}">
                                                    {{ $value ?? '—' }}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>
                    @endif
                </x-athlete.section>
            @empty
                <x-athlete.section mobile-shade="none">
                    <flux:text>No exercises are available for this program.</flux:text>
                </x-athlete.section>
            @endforelse
        </div>
    </div>
</div>
