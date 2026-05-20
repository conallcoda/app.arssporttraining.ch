<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    @if ($field->live)
        <flux:date-picker wire:model.live="{{ $wireModel }}" data-field="{{ $field->name }}" />
    @else
        <flux:date-picker wire:model.live.blur="{{ $wireModel }}" data-field="{{ $field->name }}" />
    @endif
</x-form-kit::form.field-shell>
