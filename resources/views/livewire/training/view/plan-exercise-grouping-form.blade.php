<div class="focus:outline-none">
    <flux:modal :name="$name" :flyout="$flyout" :class="$maxWidth"
        x-on:close="Livewire.dispatch('{{ $name }}.closed')"
        x-on:keydown.enter="handleModalEnterSubmit($event, $wire)">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $activeTitle ?? $title }}</flux:heading>

            <form wire:submit="submit" class="space-y-4">
                @foreach ($this->fieldsets as $item)
                    <x-form-kit::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="true" />
                @endforeach
                <div class="flex gap-2 pt-4">
                    @if ($hasOverride)
                        <flux:button type="button" variant="ghost" wire:click="resetOverride">{{ __('Reset') }}</flux:button>
                    @endif
                    <flux:button type="submit" variant="primary" class="flex-1">{{ $submitLabel }}</flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
                    </flux:modal.close>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
