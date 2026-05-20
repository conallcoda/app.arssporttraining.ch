<flux:input wire:model.live.debounce.300ms="{{ $wireModel }}" placeholder="{{ $explicitPlaceholder($field) }}" size="{{ $field->size }}" clearable {{ $attributes }}>
    <x-slot:icon>
        <flux:icon.magnifying-glass variant="micro" />
    </x-slot:icon>
</flux:input>
