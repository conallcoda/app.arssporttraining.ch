<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    @if ($field->live)
        <flux:input wire:model.live="{{ $wireModel }}" type="url"
            data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}" />
    @else
        <flux:input wire:model.live.blur="{{ $wireModel }}" type="url"
            data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}" />
    @endif
</x-form-kit::form.field-shell>
