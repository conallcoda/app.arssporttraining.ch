<div>
    <flux:modal :name="$name" variant="flyout" :class="$maxWidth"
        x-on:close="Livewire.dispatch('{{ $name }}.closed')">
        <div class="flex flex-col gap-6 p-2">
            <flux:heading size="lg">{{ $title }}</flux:heading>
            @if ($openCount > 0)
                <div wire:key="week-slot-{{ $openCount }}" class="flex flex-col gap-6">
                    @foreach ($this->fieldsets as $item)
                        <x-cms::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="false" />
                    @endforeach
                    <flux:time-picker wire:model="data.start_time" label="Start Time" time-format="24-hour" :interval="15" />
                </div>
                <div class="flex items-center gap-2">
                    <flux:button variant="primary" wire:click="submit" class="flex-1">
                        {{ $submitLabel }}
                    </flux:button>
                    <flux:button variant="ghost" wire:click="cancel">
                        {{ $cancelLabel }}
                    </flux:button>
                    @if ($isEditing)
                        <flux:button variant="ghost" icon="trash" wire:click="deleteSlot" />
                    @endif
                </div>
            @endif
        </div>
    </flux:modal>
</div>
