@php
    $bindingAttributes = new \Illuminate\View\ComponentAttributeBag([
        $field->wireModelDirective($field->disabled) => $wireModel,
    ]);
@endphp

<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    <flux:pillbox {{ $bindingAttributes }} multiple :searchable="$field->searchable"
        :placeholder="$explicitPlaceholder($field)" data-field="{{ $field->name }}">
        @foreach ($field->getOptions() as $value => $optionLabel)
            <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
        @endforeach
    </flux:pillbox>
</x-form-kit::form.field-shell>
