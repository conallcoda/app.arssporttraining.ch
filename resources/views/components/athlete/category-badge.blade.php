@props([
    'label' => null,
    'color' => null,
    'class' => '',
])

@php
    $style = null;

    if (is_string($color) && trim($color) !== '') {
        $rawColor = trim($color);
        $normalized = ltrim($rawColor, '#');

        if (preg_match('/^[0-9a-fA-F]{3}$/', $normalized)) {
            $normalized = implode('', array_map(
                fn (string $char): string => $char.$char,
                str_split($normalized)
            ));
        }

        $textColor = '#ffffff';

        if (preg_match('/^[0-9a-fA-F]{6}$/', $normalized)) {
            $red = hexdec(substr($normalized, 0, 2));
            $green = hexdec(substr($normalized, 2, 2));
            $blue = hexdec(substr($normalized, 4, 2));
            $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;
            $textColor = $luminance > 160 ? '#111827' : '#ffffff';
            $rawColor = '#'.$normalized;
            $style = sprintf('background-color: %s; color: %s;', $rawColor, $textColor);
        } elseif (preg_match('/^[#(),.%\-\sa-zA-Z0-9]+$/', $rawColor)) {
            $style = sprintf('background-color: %s; color: %s;', $rawColor, $textColor);
        }
    }
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
