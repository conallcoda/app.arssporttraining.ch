@php
    $siblingData = $prefix ? (data_get($this, $prefix) ?? []) : [];
    $isMasked = $field->isMasked($siblingData);
    $mask = $field->resolveMask($siblingData);
@endphp

<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    <flux:input.group>
        @if ($isMasked)
            <div x-data="masked_input" data-mask="{{ $mask }}" class="flex-1">
                @if ($field->live)
                    <flux:input wire:model.live="{{ $wireModel }}" type="text"
                        data-field="{{ $field->name }}" placeholder="00:00" />
                @else
                    <flux:input wire:model.live.blur="{{ $wireModel }}" type="text"
                        data-field="{{ $field->name }}" placeholder="00:00" />
                @endif
            </div>
        @else
            @if ($field->live)
                <flux:input wire:model.live="{{ $wireModel }}" type="number"
                    data-field="{{ $field->name }}" min="{{ $field->min ?? '' }}"
                    max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
            @else
                <flux:input wire:model.live.blur="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                    min="{{ $field->min ?? '' }}" max="{{ $field->max ?? '' }}"
                    step="{{ $field->step ?? '' }}" />
            @endif
        @endif
        <flux:input.group.suffix>{{ $resolvedSuffix }}</flux:input.group.suffix>
    </flux:input.group>
</x-form-kit::form.field-shell>
