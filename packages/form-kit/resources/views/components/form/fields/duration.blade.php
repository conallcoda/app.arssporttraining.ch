@php
    $siblingData = $prefix ? (data_get($this, $prefix) ?? []) : [];
    $isMasked = $field->isMasked($siblingData);
    $mask = $field->resolveMask($siblingData);
    $inputAttributes = new \Illuminate\View\ComponentAttributeBag(
        array_filter(
            [
                $field->wireModelDirective($field->disabled) => $wireModel,
                'data-field' => $field->name,
                'min' => $field->min ?? null,
                'max' => $field->max ?? null,
                'step' => $field->step ?? null,
                'disabled' => $field->disabled ? true : null,
                'readonly' => $field->disabled ? true : null,
            ],
            static fn($value) => $value !== null && $value !== '',
        ),
    );
@endphp

<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    <flux:input.group>
        @if ($isMasked)
            <flux:input :attributes="$inputAttributes" type="text" placeholder="00:00" :mask="$mask"></flux:input>
        @else
            <flux:input :attributes="$inputAttributes" type="number"></flux:input>
        @endif
        <flux:input.group.suffix>{{ $resolvedSuffix }}</flux:input.group.suffix>
    </flux:input.group>
</x-form-kit::form.field-shell>
