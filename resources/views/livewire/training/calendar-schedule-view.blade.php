<div>
    <div class="px-4 py-2 flex items-center gap-4">
        <flux:radio.group wire:model.live="weekEditMode" variant="segmented" size="sm">
            <flux:radio value="view" :label="__('View')" />
            <flux:radio value="edit" :label="__('Edit')" />
            <flux:radio value="remove" :label="__('Remove')" />
        </flux:radio.group>

        @if ($weekEditMode === 'edit')
            <div class="flex items-center gap-3 flex-1">
                <flux:select variant="listbox" searchable size="sm" wire:model.live="quickProgramId" :placeholder="__('Select program...')" :invalid="$errors->has('quickProgramId')">
                    @foreach ($this->quickProgramOptions as $id => $name)
                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($this->quickAthleteOptions->isNotEmpty())
                    <flux:select variant="listbox" searchable multiple size="sm" wire:model.live="quickSelectedAthletes" :placeholder="__('Athletes...')" :invalid="$errors->has('quickSelectedAthletes')">
                        @foreach ($this->quickAthleteOptions as $athlete)
                            <flux:select.option value="{{ $athlete['id'] }}">{{ $athlete['name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>
        @endif
    </div>

    @include('livewire.training.partials.calendar-week-grid')

    <livewire:training.week-slot-form />
</div>
