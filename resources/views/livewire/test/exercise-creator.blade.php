<flux:main>
    <div class="space-y-6">
        <flux:heading size="lg">Exercise Creator</flux:heading>

        <div class="grid grid-cols-2 gap-4 max-w-lg">
            <flux:input type="number" wire:model.live="defaultWeeks" label="Default Weeks" min="1" />
            <flux:input type="number" wire:model.live="defaultSessionsPerWeek" label="Sessions Per Week" min="1" />
        </div>

        <div class="flex items-center gap-6">
            <flux:switch wire:model.live="showPreview" label="Show Preview" />
            <flux:switch wire:model.live="showData" label="Show Data" />
            <flux:switch wire:model.live="flyout" label="Flyout" />
        </div>

        <flux:input wire:model.live="maxWidth" label="Max Width" class="max-w-xs" />

        <flux:button variant="primary" x-on:click="Livewire.dispatch('open-exercise-form')">
            Create Exercise
        </flux:button>
    </div>

    <livewire:test.exercise-form
        wire:key="exercise-form-{{ $defaultWeeks }}-{{ $defaultSessionsPerWeek }}-{{ (int) $showPreview }}-{{ (int) $showData }}-{{ (int) $flyout }}-{{ $maxWidth }}"
        name="exercise-form"
        :default-weeks="$defaultWeeks"
        :default-sessions-per-week="$defaultSessionsPerWeek"
        :show-preview="$showPreview"
        :show-data="$showData"
        :flyout="$flyout"
        :max-width="$maxWidth"
    />
</flux:main>
