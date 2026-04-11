<form wire:submit="save" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('Settings') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Configure your personal preferences.') }}</flux:text>
    </div>

    @foreach ($this->fieldsets as $item)
        @if ($item instanceof \Coda\FormKit\FormFieldsetGroup)
            <x-form-kit::form.fieldset-tabs :group="$item" />
        @else
            <x-form-kit::form.fieldset
                :fieldset="$item"
                :prefix="$item->prefix ?? 'data'"
                :showLegend="count($this->fieldsets) > 1"
            />
        @endif
    @endforeach

    <div class="flex gap-2">
        <flux:spacer />
        <flux:modal.close>
            <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
        </flux:modal.close>
        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
    </div>
</form>
