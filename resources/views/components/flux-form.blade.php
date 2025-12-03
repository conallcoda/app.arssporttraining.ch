@props(['fields', 'prefix' => null])

@foreach ($fields as $field)
    <x-flux-field :field="$field" :prefix="$prefix" />
@endforeach
