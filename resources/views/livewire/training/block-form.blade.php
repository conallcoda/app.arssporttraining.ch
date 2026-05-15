<div>
    <x-cms::modal :name="$name" variant="flyout" :max-width="$maxWidth"
        x-on:close="Livewire.dispatch('{{ $name }}.closed')">
        <x-cms::modal.header :title="$activeTitle" />
        <x-cms::modal.body class="flex flex-col gap-6">
            @if ($openCount > 0)
                <div wire:key="block-{{ $openCount }}" class="flex flex-col gap-6">
                    @foreach ($this->fieldsets as $item)
                        <x-form-kit::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="$categoryId !== null || $item->name !== 'general'" />
                    @endforeach
                </div>
            @endif
        </x-cms::modal.body>
        <x-cms::modal.footer>
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
        </x-cms::modal.footer>
    </x-cms::modal>
</div>
