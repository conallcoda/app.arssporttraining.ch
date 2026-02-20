<div>
    <flux:modal :name="$name" :flyout="$flyout" :class="$maxWidth"
        x-on:close="Livewire.dispatch('{{ $name }}.closed')"
        x-on:keydown.enter="handleModalEnterSubmit($event, $wire)">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $activeTitle ?? $title }}</flux:heading>

            <form wire:submit="submit" class="space-y-4">
                @foreach ($this->fieldsets as $item)
                    @if ($item instanceof \Coda\Cms\Form\FormFieldsetGroup)
                        <x-cms::form.fieldset-tabs :group="$item" />
                    @else
                        <x-cms::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="true" />
                    @endif
                @endforeach
                <div class="flex gap-2 pt-4">
                    <flux:button type="submit" variant="primary" class="flex-1">{{ $submitLabel }}</flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
                    </flux:modal.close>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
