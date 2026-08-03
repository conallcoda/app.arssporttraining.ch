@php
    $rawTreeValue = data_get($this, $wireModel);
    $treeValue = $field->multiple
        ? collect(is_array($rawTreeValue) ? $rawTreeValue : [])
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all()
        : (is_numeric($rawTreeValue) ? (int) $rawTreeValue : null);
    $treeOptionsJson = json_encode($field->getRenderableTreeOptions(is_array($fieldContext) ? $fieldContext : []));
    $treeKey = md5($treeOptionsJson);
@endphp

<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    <div x-data="tree_select"
        data-field="{{ $field->name }}"
        data-options="{{ $treeOptionsJson }}"
        data-value='@json($treeValue)'
        data-placeholder="{{ $explicitPlaceholder($field) }}"
        data-wire-model="{{ $wireModel }}"
        data-multiple="{{ $field->multiple ? 'true' : 'false' }}"
        data-clearable="{{ $field->clearable ? 'true' : 'false' }}"
        data-searchable="{{ $field->searchable ? 'true' : 'false' }}"
        data-leaf-only="{{ $field->treeLeafOnly ? 'true' : 'false' }}"
        wire:ignore
        wire:key="tree-select-{{ $field->name }}-{{ $treeKey }}">
        <div data-tree-select-container></div>
    </div>
</x-form-kit::form.field-shell>
