@props([
    'class' => '',
    'divider' => false,
    'padding' => null,
])

<div {{ $attributes->class([
    'mt-6',
    'border-t border-zinc-200 dark:border-zinc-700' => $divider,
    $padding,
    $class,
]) }}>
    {{ $slot }}
</div>
