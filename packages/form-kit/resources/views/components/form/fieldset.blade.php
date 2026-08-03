@props(['fieldset', 'prefix' => null, 'showLegend' => true, 'contextData' => null])

@php
    $fieldContextData = is_array($contextData) ? $contextData : $fieldset->contextData;

    $rows = $fieldset->rows !== [] ? $fieldset->rows : array_map(
        static fn ($field) => [
            'grid' => null,
            'fields' => [
                [
                    'field' => $field,
                    'class' => '',
                ],
            ],
        ],
        $fieldset->fields,
    );
@endphp

@if ($fieldset->view)
    @if ($showLegend)
    <fieldset wire:key="fieldset-{{ $prefix }}" class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-4 [&>legend+*]:!mt-0">
        <legend class="mb-0 px-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
            {{ $fieldset->label }}</legend>
        @include($fieldset->view, ['fieldset' => $fieldset, 'prefix' => $prefix, ...$fieldset->viewData])
    </fieldset>
    @else
    <div wire:key="fieldset-{{ $prefix }}" class="space-y-3">
        @include($fieldset->view, ['fieldset' => $fieldset, 'prefix' => $prefix, ...$fieldset->viewData])
    </div>
    @endif
@elseif ($showLegend)
<fieldset wire:key="fieldset-{{ $prefix }}" class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-4 [&>legend+*]:!mt-0">
    <legend class="mb-0 px-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
        {{ $fieldset->label }}</legend>
    @foreach ($rows as $row)
        @php
            $rowClass = trim('grid gap-4 '.($row['grid'] ?? ''));
        @endphp
        <div class="{{ $rowClass }}">
            @foreach ($row['fields'] as $item)
                @php
                    $field = $item['field'];
                    $fieldClass = trim((string) ($item['class'] ?? ''));
                @endphp
                <div class="{{ $fieldClass }}">
                    <x-form-kit::form.field wire:key="field-{{ $prefix }}-{{ $field->name }}" :field="$field" :prefix="$prefix" :context-data="$fieldContextData" />
                </div>
            @endforeach
        </div>
    @endforeach
</fieldset>
@else
<div wire:key="fieldset-{{ $prefix }}" class="space-y-3">
    @foreach ($rows as $row)
        @php
            $rowClass = trim('grid gap-4 '.($row['grid'] ?? ''));
        @endphp
        <div class="{{ $rowClass }}">
            @foreach ($row['fields'] as $item)
                @php
                    $field = $item['field'];
                    $fieldClass = trim((string) ($item['class'] ?? ''));
                @endphp
                <div class="{{ $fieldClass }}">
                    <x-form-kit::form.field wire:key="field-{{ $prefix }}-{{ $field->name }}" :field="$field" :prefix="$prefix" :context-data="$fieldContextData" />
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endif
