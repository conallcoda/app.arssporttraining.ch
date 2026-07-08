@php
    $value = $field->resolveValue($record);
    $displayValue = match (true) {
        $value instanceof \BackedEnum => $value->value,
        $value instanceof \UnitEnum => $value->name,
        $value instanceof \Illuminate\Support\Collection => $value->join(', '),
        is_array($value) => collect($value)->filter(fn (mixed $entry) => $entry !== null && $entry !== '')->join(', '),
        is_bool($value) => $value ? 'Yes' : 'No',
        default => $value,
    };
@endphp

@if ($field->view)
    @include($field->view, [
        'field' => $field,
        'record' => $record,
        'value' => $value,
        'displayValue' => $displayValue,
    ])
@elseif ($field->variant === 'badge')
    <flux:badge size="sm">{{ $displayValue }}</flux:badge>
@elseif ($field->variant === 'meta')
    <flux:text size="sm" variant="subtle">{{ $displayValue }}</flux:text>
@else
    <div>{{ $displayValue }}</div>
@endif
