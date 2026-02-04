@props(['fieldset', 'prefix' => null, 'showLegend' => true])

@if ($showLegend)
<fieldset wire:key="fieldset-{{ $prefix }}" class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-4 [&>legend+*]:!mt-0">
    <legend class="mb-0 px-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
        {{ $fieldset->label }}</legend>
    @foreach ($fieldset->fields as $field)
        <x-form-field wire:key="field-{{ $prefix }}-{{ $field->name }}" :field="$field" :prefix="$prefix" />
    @endforeach
</fieldset>
@else
<div wire:key="fieldset-{{ $prefix }}" class="space-y-3">
    @foreach ($fieldset->fields as $field)
        <x-form-field wire:key="field-{{ $prefix }}-{{ $field->name }}" :field="$field" :prefix="$prefix" />
    @endforeach
</div>
@endif
