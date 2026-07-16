@props(['field', 'prefix' => null, 'repeaterItems' => null, 'currentIndex' => null, 'contextData' => null])

@php
    $wireModel = $prefix ? "{$prefix}.{$field->name}" : $field->name;
    $resolvedSuffix = method_exists($field, 'resolveSuffix')
        ? $field->resolveSuffix($prefix ? (data_get($this, $prefix) ?? []) : [])
        : $resolvedSuffix ?? null;
    $fieldContext = is_array($contextData)
        ? $contextData
        : ($prefix ? (data_get($this, $prefix) ?? []) : ($this->data ?? []));
    $explicitPlaceholder = static function (mixed $field): ?string {
        if (method_exists($field, 'getExplicitPlaceholder')) {
            return $field->getExplicitPlaceholder();
        }

        return method_exists($field, 'getPlaceholder') ? $field->getPlaceholder() : null;
    };
    $rendererView = $field->getRenderView();
@endphp

@if (! view()->exists($rendererView))
    @php
        throw new RuntimeException(sprintf(
            'No FormKit renderer found for field type [%s]. Expected view [%s].',
            $field->type,
            $rendererView,
        ));
    @endphp
@endif

@include($rendererView, get_defined_vars())
