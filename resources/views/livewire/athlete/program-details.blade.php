<div>
    @if ($editorOnly)
        @include('livewire.athlete.partials.exercise-editor-form', ['editorOnly' => true])
    @else
        @php
            $progressSegments = $this->progressSegments;
            $exerciseCategory = $trainingProgram->program->exerciseCategory;
            $programCategoryLabel = $exerciseCategory?->name ?: $exerciseCategory?->short_name;
            $sectionInstructions = $this->sectionInstructions;
        @endphp
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
        :show-progress="$canRecordSession"
        :progress-segments="$progressSegments"
        :shows-section-tabs="$showsSectionTabs"
        :section-tabs="$sectionTabs"
        section-model="activeSection"
        :section-instructions="$sectionInstructions"
        :program-exercises="$programExercises"
        :is-future-session="$isFutureSession"
        :can-record-session="$canRecordSession"
        :athlete-edits-enabled="$athleteEditsEnabled"
        :auto-expand-exercise-id="$autoExpandExerciseId ?? null"
        />

        <flux:modal
            name="athlete-exercise-editor"
            class="max-w-2xl"
            x-on:close="$wire.cancelExerciseEditor()"
        >
            @include('livewire.athlete.partials.exercise-editor-form', ['editorOnly' => false])
        </flux:modal>
    @endif
</div>
