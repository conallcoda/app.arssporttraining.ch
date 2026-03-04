<div class="flex items-center gap-2">
    @if ($field->colorMap[$value] ?? null)
        <span class="size-3 rounded-full shrink-0" style="{{ \Coda\Cms\Support\ColorPalette::solid($field->colorMap[$value]) }}"></span>
    @endif
    {{ $label }}
</div>
