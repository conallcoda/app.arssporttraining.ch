@php
    $progressSegments = $this->progressSegments;
    $exerciseCategory = $trainingProgram->program->exerciseCategory;
    $programCategoryLabel = $exerciseCategory?->name ?: $exerciseCategory?->short_name;
@endphp
<div>
    @php
        $sectionTabItems = collect($sectionTabs)
            ->map(fn (array $tab): array => [
                'name' => $tab['key'],
                'label' => $tab['label'],
            ])
            ->all();
    @endphp
    <div
        class="sticky top-0 z-20 md:mt-20 bg-zinc-100/95 backdrop-blur dark:bg-zinc-800/95">
        <div class="w-full md:mx-auto md:max-w-[900px] border-b border-zinc-200 dark:border-zinc-700">
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

        @if (! $isFutureSession)
            <div class="border-t border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900/40">
                <x-athlete.recorded-progress :segments="$progressSegments" label-align="center" />
            </div>
        @endif

        @if ($showsSectionTabs)
            <div class="border-t border-zinc-200 bg-zinc-50 px-0 py-0 dark:border-zinc-700 dark:bg-zinc-900/40">
                <x-athlete.segmented-tabs :tabs="$sectionTabItems" model="activeSection" />
            </div>
        @endif
        </div>
    </div>

    <div class="w-full md:mx-auto md:max-w-[900px] px-0 py-0">
        <div class="space-y-0">
            @forelse ($programExercises as $exercise)
                <x-athlete.section :row="$loop->iteration" class="py-0" content-class="space-y-0 py-3"
                    wire:key="exercise-{{ $exercise->id }}" x-data="{ open: false }">
                    <div x-on:click="open = !open" x-on:keydown.enter.prevent="open = !open"
                        x-on:keydown.space.prevent="open = !open" :aria-expanded="open" role="button" tabindex="0"
                        class="flex w-full cursor-pointer items-start justify-between gap-3">
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <div class="text-sm font-medium leading-tight text-zinc-800 dark:text-white">
                                {{ $exercise->groupLabel ? $exercise->groupLabel . ' - ' . $exercise->name : $exercise->name }}
                            </div>

                            @if (! $isFutureSession)
                                <span
                                    class="status-badge inline-flex rounded-md px-2 py-1 text-[10px] font-medium"
                                    style="--status-bar-light: {{ $exercise->statusColor['light'] }}; --status-bar-dark: {{ $exercise->statusColor['dark'] }};">
                                    {{ $exercise->statusLabel }}
                                </span>
                            @endif
                        </div>
                        <flux:icon.chevron-up x-show="open" x-cloak
                            class="mt-1 size-5 shrink-0 text-zinc-500 dark:text-zinc-400" />
                        <flux:icon.chevron-down x-show="!open" x-cloak
                            class="mt-1 size-5 shrink-0 text-zinc-500 dark:text-zinc-400" />
                    </div>

                    <div x-show="open" x-cloak x-collapse x-bind:class="open ? 'pt-3' : 'pt-0'" class="space-y-3">
                        <div class="space-y-2">
                            @if (!empty($exercise->equipmentBadges) || !empty($exercise->modifierBadges))
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($exercise->equipmentBadges as $badge)
                                        <flux:badge color="blue" size="sm" class="text-[10px]">
                                            {{ $badge }}</flux:badge>
                                    @endforeach

                                    @foreach ($exercise->modifierBadges as $badge)
                                        <flux:badge size="sm" class="text-[10px]">{{ $badge }}
                                        </flux:badge>
                                    @endforeach
                                </div>
                            @endif

                            @if ($exercise->weekDetails)
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($exercise->weekDetails as $detail)
                                        <span
                                            class="inline-flex rounded-md bg-zinc-200 px-2 py-1 text-[10px] font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                                            {{ $detail }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($exercise->notes)
                                <div class="space-y-2">
                                    @foreach ($exercise->notes as $note)
                                        <div class="rounded-lg bg-zinc-50 px-2.5 py-2 dark:bg-zinc-800">
                                            <div
                                                class="text-[10px] font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                {{ $note->label }}
                                            </div>
                                            <div
                                                class="mt-1 whitespace-pre-line text-xs text-zinc-700 dark:text-zinc-200">
                                                {{ $note->value }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($exercise->instructions)
                                <flux:text
                                    class="whitespace-pre-line text-[11px] text-zinc-600 dark:text-zinc-300">
                                    {{ $exercise->instructions }}
                                </flux:text>
                            @endif

                            @if ($exercise->videoUrl || !empty($exercise->photoUrls) || ($athleteEditsEnabled && ! $isFutureSession))
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        @if ($exercise->videoUrl)
                                            <button type="button"
                                                x-on:click="$dispatch('open-youtube-player', { url: @js($exercise->videoUrl) })"
                                                class="inline-flex size-9 items-center justify-center rounded-md bg-zinc-200 text-zinc-700 transition hover:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-600"
                                                aria-label="Watch video">
                                                <flux:icon.play-circle class="size-5" />
                                            </button>
                                        @endif

                                        @if (!empty($exercise->photoUrls))
                                            <button type="button"
                                                x-on:click="$dispatch('open-exercise-gallery', { images: @js($exercise->photoUrls), index: 0 })"
                                                class="inline-flex size-9 items-center justify-center rounded-md bg-zinc-200 text-zinc-700 transition hover:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-600"
                                                aria-label="View gallery">
                                                <flux:icon.camera class="size-5" />
                                            </button>
                                        @endif
                                    </div>

                                    @if ($athleteEditsEnabled && ! $isFutureSession)
                                        <button type="button"
                                            wire:click="openExerciseEditor({{ $exercise->id }})"
                                            class="inline-flex size-9 items-center justify-center rounded-md bg-zinc-900 text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                                            aria-label="Edit exercise values">
                                            <flux:icon.pencil class="size-4" />
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if ($exercise->sessionRows)
                            <div>
                                <div class="overflow-x-auto border border-zinc-300 dark:border-zinc-600">
                                    <table class="min-w-full border-collapse text-xs">
                                        <thead>
                                            <tr class="bg-zinc-100 dark:bg-zinc-800">
                                                <th
                                                    class="sticky left-0 z-10 border-b border-r border-zinc-300 bg-zinc-100 px-2 py-1.5 text-center font-semibold dark:border-zinc-600 dark:bg-zinc-800 whitespace-nowrap">
                                                    {{ $exercise->setLabel }}
                                                </th>
                                                @foreach ($exercise->sessionRows as $row)
                                                    <th
                                                        class="border-b border-r border-zinc-300 px-2 py-1.5 text-center font-semibold last:border-r-0 dark:border-zinc-600 whitespace-nowrap {{ $row->labelClass }}">
                                                        {{ $row->label }}
                                                    </th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @for ($set = 0; $set < $exercise->setCount; $set++)
                                                <tr>
                                                    <td
                                                        class="sticky left-0 z-10 border-r border-t border-zinc-300 bg-zinc-100 px-2 py-1.5 text-center font-medium dark:border-zinc-600 dark:bg-zinc-800 whitespace-nowrap">
                                                        {{ $set + 1 }}
                                                    </td>
                                                    @foreach ($exercise->sessionRows as $row)
                                                        <td
                                                            class="border-r border-t border-zinc-300 px-2 py-1.5 text-center last:border-r-0 dark:border-zinc-600 whitespace-nowrap {{ $row->valueClasses[$set] ?? '' }}">
                                                            {{ $row->values[$set] ?? '—' }}
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endfor
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if (! $isFutureSession)
                            <div class="flex flex-wrap justify-end gap-2">
                                <flux:button size="sm" variant="primary"
                                    wire:click="markExerciseCompleted({{ $exercise->id }})" x-on:click="open = false">
                                    Mark Done
                                </flux:button>
                                <flux:button size="sm" variant="ghost"
                                    wire:click="markExerciseSkipped({{ $exercise->id }})" x-on:click="open = false">
                                    Skip
                                </flux:button>
                            </div>
                        @endif
                    </div>
                </x-athlete.section>
            @empty
                <x-athlete.section mobile-shade="none">
                    <flux:text>No exercises are available for this program.</flux:text>
                </x-athlete.section>
            @endforelse
        </div>
    </div>

    <flux:modal name="athlete-exercise-editor" class="max-w-2xl" x-on:close="$wire.cancelExerciseEditor()">
        <div class="space-y-5">
            <div class="min-w-0">
                <flux:heading size="lg">Edit</flux:heading>
            </div>

            @if ($this->editingExercise)
                <form wire:submit="saveExerciseEdits" class="space-y-5">
                    @if (count($this->editSetTabs) > 1)
                        <flux:select wire:model.live="activeEditSet" variant="listbox" class="w-full">
                            @foreach ($this->editSetTabs as $tab)
                                <flux:select.option :value="$tab['name']">{{ $tab['label'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                    @foreach ($this->editSetPanels as $panel)
                        <div wire:key="edit-set-panel-{{ $panel['id'] }}"
                            @class([
                                'space-y-4' => true,
                                'hidden' => count($this->editSetTabs) > 1 && $activeEditSet !== $panel['tab'],
                            ])>
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                {{ $panel['label'] }}
                            </div>

                            @foreach ($panel['fields'] as $field)
                                <x-form-kit::form.field :field="$field" :prefix="'editValues.'.$panel['id']" />
                            @endforeach
                        </div>
                    @endforeach

                    <div class="flex items-center gap-2 pt-2">
                        <flux:button type="submit" variant="primary" class="flex-1">
                            Save values
                        </flux:button>
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" wire:click="cancelExerciseEditor">
                                Cancel
                            </flux:button>
                        </flux:modal.close>
                    </div>
                </form>
            @endif
        </div>
    </flux:modal>
</div>
