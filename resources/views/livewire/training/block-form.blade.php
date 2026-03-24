<div>
    <flux:modal :name="$name" variant="flyout" :class="$maxWidth"
        x-on:close="Livewire.dispatch('{{ $name }}.closed')">
        <div class="flex flex-col gap-6 p-2">
            <flux:heading size="lg">{{ $activeTitle }}</flux:heading>
            @if ($openCount > 0)
                <div wire:key="block-{{ $openCount }}" class="flex flex-col gap-6">
                    @foreach ($this->fieldsets as $item)
                        <x-cms::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="$categoryId !== null || $item->name !== 'general'" />
                    @endforeach
                </div>
                <div class="flex items-center gap-2">
                    <flux:button variant="primary" wire:click="submit" class="flex-1">
                        {{ $submitLabel }}
                    </flux:button>
                    @if ($parentBlockId && $isEditing)
                        <flux:button variant="ghost" wire:click="resetToParentDefaults">
                            {{ __('Reset') }}
                        </flux:button>
                    @endif
                    <flux:button variant="ghost" wire:click="cancel">
                        {{ $cancelLabel }}
                    </flux:button>
                    @if (! $parentBlockId && $isEditing)
                        <flux:button variant="ghost" icon="trash" wire:click="deleteBlock" />
                    @endif
                </div>
            @endif
        </div>
    </flux:modal>
</div>
