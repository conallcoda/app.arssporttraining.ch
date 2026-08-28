<div>
    <x-cms::modal
        :name="$this->modalName()"
        variant="flyout"
        max-width="max-w-2xl"
        x-on:close="$wire.flyoutClosed()"
    >
        <x-cms::modal.header :title="__('Edit')" />
        <x-cms::modal.body class="space-y-5">
            <div class="flex flex-wrap items-center gap-2">
                <x-athlete.category-badge
                    :label="$exerciseProgram->name"
                    :color="$exerciseProgram->exerciseCategory?->color"
                    class="normal-case text-sm"
                />

                @if ($this->athlete)
                    <flux:badge color="zinc" size="sm" class="px-2.5 py-1 text-sm">
                        {{ $this->athlete->forename }} {{ $this->athlete->surname }}
                    </flux:badge>
                @endif
            </div>

            @if ($open && $this->recordingSlot)
                <livewire:athlete.program-details
                    :key="'athlete-editor-' . $this->recordingSlot->id . '-' . ($exerciseId ?? 'none') . '-v' . $openVersion"
                    :date="$this->recordingSlot->datetime->format('Y-m-d')"
                    :preview-mode="true"
                    :record-mode="true"
                    :editor-only="true"
                    :preview-user-id="$userId"
                    :preview-slot-id="$this->recordingSlot->id"
                    :training-program="$this->recordingSlot->trainingProgram"
                    :initial-section="$section"
                    :initial-exercise-id="$exerciseId"
                    :initial-exercise-sort="$exerciseSort"
                />
            @endif
        </x-cms::modal.body>
    </x-cms::modal>
</div>
