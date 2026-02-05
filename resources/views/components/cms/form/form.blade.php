@props(['fields', 'prefix' => null])

@foreach ($fields as $item)
    @if ($item instanceof \App\Cms\Form\FormFieldset)
        <x-cms.form.fieldset :fieldset="$item" :prefix="$prefix" />
    @else
        <x-cms.form.field :field="$item" :prefix="$prefix" />
    @endif
@endforeach
