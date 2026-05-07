@php
    $progressSegments = $this->progressSegments;
    $exerciseCategory = $trainingProgram->program->exerciseCategory;
    $programCategoryLabel = $exerciseCategory?->name ?: $exerciseCategory?->short_name;
@endphp
<div>
    <x-athlete.program-session-detail
        back-mode="{{ $previewMode ? 'action' : 'link' }}"
        :back-href="$this->backUrl"
        back-action="exitPreviewDetails"
        :back-label="$this->backLabel"
        :back-use-history="$this->from !== null"
        :date-label="\Carbon\CarbonImmutable::parse($date)->locale(app()->getLocale())->translatedFormat('D, d.m.Y')"
        :time-label="$this->currentSlot->datetime->format('gA')"
        :category-label="$programCategoryLabel"
        :category-color="$exerciseCategory?->color"
        :show-progress="! $isFutureSession"
        :progress-segments="$progressSegments"
        :shows-section-tabs="$showsSectionTabs"
        :section-tabs="$sectionTabs"
        section-model="activeSection"
        :program-exercises="$programExercises"
        :is-future-session="$isFutureSession"
        :athlete-edits-enabled="$athleteEditsEnabled"
    />

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
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                    {{ $panel['label'] }}
                                </div>

                                @if ($panel['isSkipped'])
                                    <flux:button type="button" size="xs" variant="ghost" wire:click="markEditSetPending({{ $panel['id'] }})">
                                        Unskip set
                                    </flux:button>
                                @else
                                    <flux:button type="button" size="xs" variant="ghost" wire:click="markEditSetSkipped({{ $panel['id'] }})">
                                        Skip set
                                    </flux:button>
                                @endif
                            </div>

                            @if ($panel['isSkipped'])
                                <div class="rounded-lg border border-sky-300/40 bg-sky-100/70 px-3 py-2 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200">
                                    This set was skipped.
                                </div>
                            @else
                                @foreach ($panel['fields'] as $field)
                                    <x-form-kit::form.field :field="$field" :prefix="'editValues.'.$panel['id']" />
                                @endforeach
                            @endif
                        </div>
                    @endforeach

                    <div class="flex items-center gap-2 pt-2">
                        <flux:button type="submit" variant="primary" class="flex-1">
                            Save
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
