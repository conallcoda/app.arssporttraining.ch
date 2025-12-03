@props(['fields', 'prefix' => null])

@foreach ($fields as $item)
    @if ($item instanceof \App\Data\Form\FluxFieldset)
        <x-flux-fieldset :fieldset="$item" :prefix="$prefix" />
    @else
        <x-flux-field :field="$item" :prefix="$prefix" />
    @endif
@endforeach
