<div>
    <form wire:submit="saveProgram" class="space-y-4">
        @foreach ($this->fieldsets as $item)
            <x-cms::form.fieldset
                :fieldset="$item"
                :prefix="$item->prefix ?? 'data'"
                :showLegend="count($this->fieldsets) > 1"
            />
        @endforeach
        <div class="flex gap-2 pt-4">
            <flux:button type="submit" variant="primary" class="flex-1">Save</flux:button>
            <flux:button variant="ghost" x-on:click="Livewire.dispatch('portal:close')">Cancel</flux:button>
        </div>
    </form>
</div>
