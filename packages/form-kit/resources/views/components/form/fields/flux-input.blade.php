@props([
    'field',
    'wireModel',
    'resolvedSuffix' => null,
    'explicitPlaceholder' => null,
    'placeholder' => null,
    'inputType' => null,
    'inputClass' => null,
    'mask' => null,
    'componentAttributes' => null,
])

@php
    $placeholder = $placeholder ?? $explicitPlaceholder($field);
    $componentAttributes = $componentAttributes ?? new \Illuminate\View\ComponentAttributeBag();
    $inputType = $inputType ?? ($field->inputType ?? ($field->type ?? 'text'));
    $inputClass = trim(
        implode(
            ' ',
            array_filter([
                $inputClass ?? null,
                property_exists($field, 'uppercase') && $field->uppercase ? 'uppercase' : null,
                $componentAttributes->get('class'),
            ]),
        ),
    );
    $mask = $mask ?? (property_exists($field, 'mask') ? $field->mask : null);
    $wireModelDirective = method_exists($field, 'wireModelDirective')
        ? $field->wireModelDirective($field->disabled)
        : 'wire:model';
    $fluxInputAttributes = new \Illuminate\View\ComponentAttributeBag(
        array_filter(
            [
                $wireModelDirective => $wireModel,
                'data-field' => $field->name,
                'class:input' => $inputClass !== '' ? $inputClass : null,
                'class' => $inputClass !== '' ? $inputClass : null,
                'maxlength' => property_exists($field, 'maxLength') ? $field->maxLength : null,
                'min' => property_exists($field, 'min') ? $field->min : null,
                'max' => property_exists($field, 'max') ? $field->max : null,
                'step' => property_exists($field, 'step') ? $field->step : null,
                'disabled' => $field->disabled ? true : null,
                'readonly' => $field->disabled ? true : null,
            ],
            static fn($value) => $value !== null && $value !== '',
        ),
    );
    $needsGroup = filled($resolvedSuffix);
@endphp

<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    @if ($needsGroup)
        <flux:input.group>
            <flux:input :attributes="$fluxInputAttributes" :type="$inputType" :placeholder="$placeholder" :mask="$mask"></flux:input>
            <flux:input.group.suffix>{{ $resolvedSuffix }}</flux:input.group.suffix>
        </flux:input.group>
    @else
        <flux:input :attributes="$fluxInputAttributes" :type="$inputType" :placeholder="$placeholder" :mask="$mask"></flux:input>
    @endif
</x-form-kit::form.field-shell>
