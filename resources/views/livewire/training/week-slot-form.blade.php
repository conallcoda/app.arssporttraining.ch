<div>
    <x-cms::modal :name="$name" variant="flyout" :max-width="$maxWidth"
        x-on:close="Livewire.dispatch('{{ $name }}.closed')">
        <x-cms::modal.header :title="$isEditing ? __('Edit Slot') : __('Add Slot')" />
        <x-cms::modal.body class="flex flex-col gap-6">
            @if ($openCount > 0)
                <div wire:key="week-slot-{{ $openCount }}" class="flex flex-col gap-6">
                    @foreach ($this->fieldsets as $item)
                        <x-form-kit::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="false" />
                    @endforeach
                    <flux:time-picker wire:model="data.start_time" :label="__('Start Time')" time-format="24-hour" :interval="15" />
                    @if (count($members) > 0)
                        <flux:field>
                            <flux:label>{{ __('Athletes') }}</flux:label>
                            <div class="flex flex-col gap-1">
                                @foreach ($members as $member)
                                    <flux:checkbox
                                        wire:model="selectedMembers"
                                        :label="$member['name']"
                                        :value="$member['id']"
                                    />
                                @endforeach
                            </div>
                        </flux:field>
                    @endif
                </div>
            @endif
        </x-cms::modal.body>
        <x-cms::modal.footer>
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
        </x-cms::modal.footer>
    </x-cms::modal>
</div>
