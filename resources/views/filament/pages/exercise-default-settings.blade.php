<x-filament-panels::page>
    @php
        $incompleteIds = $this->getIncompleteExerciseIds();
        $exerciseIds = collect($this->data['exercise_defaults'] ?? [])->pluck('exercise_id')->toArray();
    @endphp

    <div class="mb-4">
        <x-filament::tabs>
            <x-filament::tabs.item
                :active="$this->filter === 'all'"
                :href="'?filter=all'"
                tag="a"
            >
                All
            </x-filament::tabs.item>
            <x-filament::tabs.item
                :active="$this->filter === 'incomplete'"
                :href="'?filter=incomplete'"
                tag="a"
                :badge="count($incompleteIds)"
            >
                Incomplete
            </x-filament::tabs.item>
        </x-filament::tabs>
    </div>

    <form wire:submit="save" class="fi-page-content">
        <div
            x-data="{
                filter: '{{ $this->filter }}',
                incompleteIds: @js($incompleteIds),
                exerciseIds: @js($exerciseIds),
                applyFilter() {
                    const rows = this.$el.querySelectorAll('tbody tr');
                    rows.forEach((row, index) => {
                        const exerciseId = this.exerciseIds[index];
                        if (this.filter === 'all') {
                            row.style.display = '';
                        } else {
                            row.style.display = this.incompleteIds.includes(exerciseId) ? '' : 'none';
                        }
                    });
                }
            }"
            x-init="$nextTick(() => applyFilter())"
        >
            {{ $this->form }}
        </div>

        <div class="mt-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <x-filament::button type="submit">
                    {{ __('db-config::db-config.save') }}
                </x-filament::button>
                <x-filament::button color="gray" wire:click="export">
                    Export CSV
                </x-filament::button>
            </div>
            <small class="text-success">
                {{ __('db-config::db-config.last_updated') }}:
                {{ $this->lastUpdatedAt(timezone: 'UTC', format: 'F j, Y, H:i:s') . ' UTC' ?? 'Never' }}
            </small>
        </div>
    </form>
</x-filament-panels::page>
