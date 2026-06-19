@props([
    'class' => '',
    'padding' => null,
])

<div data-modal-focus-content {{ $attributes->class([$padding, $class]) }}>
    {{ $slot }}
</div>
