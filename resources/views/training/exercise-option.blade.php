@php
    /** @var \App\Models\Exercise\Exercise $option */
    $category = $option->category;
    $categoryColor = $category?->color;
@endphp
<div class="flex items-center justify-between gap-2 py-0.5 w-full text-xs">
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 min-w-0">
        <span class="font-medium truncate">{{ $option->name }}</span>
        @foreach ($option->equipment as $tag)
            <flux:badge size="sm" color="blue" class="text-[10px] px-1.5 py-0">{{ $tag->short_name ?: $tag->name }}</flux:badge>
        @endforeach
        @foreach ($option->modifiers as $tag)
            <flux:badge size="sm" color="zinc" class="text-[10px] px-1.5 py-0">{{ $tag->short_name ?: $tag->name }}</flux:badge>
        @endforeach
    </div>
    @if ($category)
        @if ($categoryColor)
            <flux:badge size="sm" class="{{ \Coda\Cms\Support\ColorPalette::lightBadge($categoryColor) }} shrink-0 text-[10px] px-1.5 py-0">
                {{ $category->short_name ?: $category->name }}
            </flux:badge>
        @else
            <flux:badge size="sm" class="shrink-0 text-[10px] px-1.5 py-0">{{ $category->short_name ?: $category->name }}</flux:badge>
        @endif
    @endif
</div>
