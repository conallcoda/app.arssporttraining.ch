@props([
    'label' => null,
    'color' => null,
    'class' => '',
])

@php
    $style = \App\Support\Ui\CategoryColorStyle::resolve($color);
@endphp

@if ($label)
    <span
        {{ $attributes->class([
            'inline-flex rounded-md px-2 py-1 text-sm uppercase',
            'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200' => ! $style,
            $class,
        ]) }}
        @if ($style) style="{{ $style }}" @endif
    >
        {{ $label }}
    </span>
@endif
