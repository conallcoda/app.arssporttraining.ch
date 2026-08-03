@props(['fields', 'prefix' => null])

@foreach ($fields as $item)
    @if ($item instanceof \Coda\FormKit\FormFieldset)
        <x-form-kit::form.fieldset :fieldset="$item" :prefix="$prefix" :context-data="$item->contextData" />
    @else
        <x-form-kit::form.field :field="$item" :prefix="$prefix" />
    @endif
@endforeach
