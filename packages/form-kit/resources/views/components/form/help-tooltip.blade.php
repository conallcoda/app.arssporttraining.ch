@props(['content' => null, 'position' => 'bottom', 'align' => 'center'])

<x-help-popover :content="$content" :position="$position" :align="$align" label="Show help">
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @endif
</x-help-popover>
