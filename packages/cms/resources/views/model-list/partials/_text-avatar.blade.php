@props([
    'imageUrl' => null,
    'imageAlt' => '',
    'showInitialsFallback' => true,
])

<flux:avatar
    :src="$imageUrl"
    :name="$imageAlt"
    size="sm"
    :no-initials-fallback="! $showInitialsFallback"
/>
