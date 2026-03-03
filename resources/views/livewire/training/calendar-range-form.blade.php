<div>
    <flux:modal :name="$name" variant="flyout" :class="$maxWidth"
        x-on:close="Livewire.dispatch('{{ $name }}.closed')">
        <div class="flex flex-col gap-6 p-2">
            <flux:heading size="lg">{{ $title }}</flux:heading>
            @if ($openCount > 0)
                <div wire:key="calendar-range-{{ $openCount }}-{{ $data['period'] ?? 'month' }}" class="flex flex-col gap-6">
                    @foreach ($this->fieldsets as $item)
                        <x-cms::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="true" />
                    @endforeach
                </div>
                <flux:button variant="primary" wire:click="submit" class="w-full">
                    {{ $submitLabel }}
                </flux:button>
            @endif
        </div>
    </flux:modal>
</div>
