<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    @if ($resolvedSuffix)
        <flux:input.group>
            @if ($field->disabled)
                <flux:input wire:model="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                    placeholder="{{ $explicitPlaceholder($field) }}" min="{{ $field->min ?? '' }}"
                    max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" disabled readonly />
            @elseif ($field->live)
                <flux:input wire:model.live="{{ $wireModel }}" type="number"
                    data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}"
                    min="{{ $field->min ?? '' }}" max="{{ $field->max ?? '' }}"
                    step="{{ $field->step ?? '' }}" />
            @else
                <flux:input wire:model.live.blur="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                    placeholder="{{ $explicitPlaceholder($field) }}" min="{{ $field->min ?? '' }}"
                    max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
            @endif
            <flux:input.group.suffix>{{ $resolvedSuffix }}</flux:input.group.suffix>
        </flux:input.group>
    @else
        @if ($field->disabled)
            <flux:input wire:model="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                placeholder="{{ $explicitPlaceholder($field) }}" min="{{ $field->min ?? '' }}"
                max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" disabled readonly />
        @elseif ($field->live)
            <flux:input wire:model.live="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                placeholder="{{ $explicitPlaceholder($field) }}" min="{{ $field->min ?? '' }}"
                max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
        @else
            <flux:input wire:model.live.blur="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                placeholder="{{ $explicitPlaceholder($field) }}" min="{{ $field->min ?? '' }}"
                max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
        @endif
    @endif
</x-form-kit::form.field-shell>
