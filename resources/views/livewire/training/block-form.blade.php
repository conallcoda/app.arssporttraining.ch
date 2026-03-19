<div>
    <flux:modal :name="$name" variant="flyout" :class="$maxWidth"
        x-on:close="Livewire.dispatch('{{ $name }}.closed')">
        <div class="flex flex-col gap-6 p-2">
            <flux:heading size="lg">{{ $isEditing ? __('Edit Block') : __('Add Block') }}</flux:heading>
            @if ($openCount > 0)
                <div wire:key="block-{{ $openCount }}" class="flex flex-col gap-6">
                    @foreach ($this->fieldsets as $item)
                        <x-cms::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" />
                    @endforeach
                    @if ($categorySlug === 'strength')
                        <flux:switch wire:model="data.config.autoRecord1rm" :label="__('Automatically record 1RM?')" />
                    @endif
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
                <div class="flex items-center gap-2">
                    <flux:button variant="primary" wire:click="submit" class="flex-1">
                        {{ $submitLabel }}
                    </flux:button>
                    <flux:button variant="ghost" wire:click="cancel">
                        {{ $cancelLabel }}
                    </flux:button>
                    @if ($isEditing)
                        <flux:button variant="ghost" icon="trash" wire:click="deleteBlock" />
                    @endif
                </div>
            @endif
        </div>
    </flux:modal>
</div>
