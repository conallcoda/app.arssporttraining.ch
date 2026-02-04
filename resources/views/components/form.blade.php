@props(['fields', 'prefix' => null])

@foreach ($fields as $item)
    @if ($item instanceof \App\Form\FormFieldset)
        <x-form-fieldset :fieldset="$item" :prefix="$prefix" />
    @else
        <x-form-field :field="$item" :prefix="$prefix" />
    @endif
@endforeach
