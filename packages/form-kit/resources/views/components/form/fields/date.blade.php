@php
    $datePickerAttributes = new \Illuminate\View\ComponentAttributeBag([
        $field->wireModelDirective($field->disabled) => $wireModel,
        'data-field' => $field->name,
    ]);
@endphp

<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    <flux:date-picker
        :attributes="$datePickerAttributes"
        :selectable-header="$field->selectableHeader"
        :with-inputs="$field->withInputs"
        :clearable="$field->clearable"
        :type="$field->pickerType"
    ></flux:date-picker>
</x-form-kit::form.field-shell>
