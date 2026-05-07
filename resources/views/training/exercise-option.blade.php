@php
    /** @var \App\Models\Exercise\Exercise $option */
    $category = $option->category;
    $categoryColor = $category?->color;
@endphp
<div class="flex items-center justify-between gap-2 py-0.5 w-full text-xs">
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 min-w-0">
        <span class="font-medium truncate">{{ $option->name }}</span>
        @foreach ($option->equipment as $tag)
            <span class="inline-flex items-center rounded-full bg-blue-100 px-1.5 py-0 text-[10px] leading-5 text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">{{ $tag->short_name ?: $tag->name }}</span>
        @endforeach
        @foreach ($option->modifiers as $tag)
            <span class="inline-flex items-center rounded-full bg-zinc-100 px-1.5 py-0 text-[10px] leading-5 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $tag->short_name ?: $tag->name }}</span>
        @endforeach
    </div>
    @if ($category)
        @if ($categoryColor)
            <span class="inline-flex items-center rounded-full px-1.5 py-0 text-[10px] leading-5 shrink-0 {{ \Coda\Cms\Support\ColorPalette::lightBadge($categoryColor) }}">
                {{ $category->short_name ?: $category->name }}
            </span>
        @else
            <span class="inline-flex items-center rounded-full bg-zinc-100 px-1.5 py-0 text-[10px] leading-5 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 shrink-0">{{ $category->short_name ?: $category->name }}</span>
        @endif
    @endif
</div>
