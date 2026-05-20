@php
    $textInputClass = $field->uppercase ? 'uppercase' : null;
@endphp

<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    @if ($resolvedSuffix)
        <flux:input.group>
            @if ($field->mask)
                <div x-data="masked_input" data-mask="{{ $field->mask }}" class="flex-1">
                    @if ($field->live)
                        <flux:input wire:model.live="{{ $wireModel }}" type="{{ $field->inputType }}"
                            data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}"
                            :maxlength="$field->maxLength" :class="$textInputClass" />
                    @else
                        <flux:input wire:model.live.blur="{{ $wireModel }}" type="{{ $field->inputType }}"
                            data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}"
                            :maxlength="$field->maxLength" :class="$textInputClass" />
                    @endif
                </div>
            @else
                @if ($field->live)
                    <flux:input wire:model.live="{{ $wireModel }}" type="{{ $field->inputType }}"
                        data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}"
                        :maxlength="$field->maxLength" :class="$textInputClass" />
                @else
                    <flux:input wire:model.live.blur="{{ $wireModel }}" type="{{ $field->inputType }}"
                        data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}"
                        :maxlength="$field->maxLength" :class="$textInputClass" />
                @endif
            @endif
            <flux:input.group.suffix>{{ $resolvedSuffix }}</flux:input.group.suffix>
        </flux:input.group>
    @elseif ($field->mask)
        <div x-data="masked_input" data-mask="{{ $field->mask }}">
            @if ($field->live)
                <flux:input wire:model.live="{{ $wireModel }}" type="{{ $field->inputType }}"
                    data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}"
                    :maxlength="$field->maxLength" :class="$textInputClass" />
            @else
                <flux:input wire:model.live.blur="{{ $wireModel }}" type="{{ $field->inputType }}"
                    data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}"
                    :maxlength="$field->maxLength" :class="$textInputClass" />
            @endif
        </div>
    @else
        @if ($field->live)
            <flux:input wire:model.live="{{ $wireModel }}" type="{{ $field->inputType }}"
                data-field="{{ $field->name }}" placeholder="{{ $explicitPlaceholder($field) }}"
                :maxlength="$field->maxLength" :class="$textInputClass" />
        @else
            <flux:input wire:model.live.blur="{{ $wireModel }}" type="{{ $field->inputType }}" data-field="{{ $field->name }}"
                placeholder="{{ $explicitPlaceholder($field) }}"
                :maxlength="$field->maxLength" :class="$textInputClass" />
        @endif
    @endif
</x-form-kit::form.field-shell>
