@php
    $textareaAttributes = new \Illuminate\View\ComponentAttributeBag([
        $field->wireModelDirective($field->disabled) => $wireModel,
        'data-field' => $field->name,
        'placeholder' => $explicitPlaceholder($field),
    ]);
@endphp

<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    @if ($field->autosize)
        <flux:textarea :attributes="$textareaAttributes" rows="auto"></flux:textarea>
    @else
        <flux:textarea :attributes="$textareaAttributes"></flux:textarea>
    @endif
</x-form-kit::form.field-shell>
