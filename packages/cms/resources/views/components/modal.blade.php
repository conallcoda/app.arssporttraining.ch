@props([
    'name',
    'flyout' => false,
    'variant' => null,
    'position' => null,
    'maxWidth' => null,
    'scroll' => null,
    'dismissible' => null,
    'padding' => null,
])

<flux:modal
    :name="$name"
    :flyout="$flyout"
    :variant="$variant"
    :position="$position"
    :scroll="$scroll"
    :dismissible="$dismissible"
    :closable="false"
    :class="$maxWidth"
    {{ $attributes }}
>
    <div @class(['flex h-full min-h-0 flex-col', $padding])>
        {{ $slot }}
    </div>
</flux:modal>
