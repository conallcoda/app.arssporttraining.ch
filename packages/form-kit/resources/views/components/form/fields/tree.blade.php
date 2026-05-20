@php
    $rawTreeValue = data_get($this, $wireModel);
    $treeValue = is_numeric($rawTreeValue) ? (int) $rawTreeValue : null;
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
        data-multiple="false"
        data-clearable="true"
        data-searchable="true"
        data-leaf-only="false"
        wire:ignore
        wire:key="tree-{{ $field->name }}-{{ $treeKey }}">
        <div data-tree-select-container></div>
    </div>
</x-form-kit::form.field-shell>
