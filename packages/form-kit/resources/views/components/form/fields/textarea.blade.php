<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    @if ($field->live)
        @if ($field->autosize)
            <flux:textarea wire:model.live="{{ $wireModel }}" rows="auto"
                data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}" />
        @else
            <flux:textarea wire:model.live="{{ $wireModel }}"
                data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}" />
        @endif
    @else
        @if ($field->autosize)
            <flux:textarea wire:model.live.blur="{{ $wireModel }}" rows="auto"
                data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}" />
        @else
            <flux:textarea wire:model.live.blur="{{ $wireModel }}"
                data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}" />
        @endif
    @endif
</x-form-kit::form.field-shell>
