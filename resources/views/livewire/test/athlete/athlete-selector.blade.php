<div class="w-64">
    <flux:select
        wire:model.live="selectedAthleteId"
        variant="listbox"
        searchable
        clearable
        placeholder="Select an athlete..."
        size="sm"
    >
        @foreach ($this->athletes as $athlete)
            <flux:select.option :value="$athlete->id">{{ $athlete->name }}</flux:select.option>
        @endforeach
    </flux:select>
</div>
