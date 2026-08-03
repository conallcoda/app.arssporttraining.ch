@php
    $previewUrl = $item->preview_url ?? null;
    $mediaUrl = $item->url ?? null;
@endphp

<flux:card wire:key="media-card-{{ $item->id }}" class="flex h-full flex-col overflow-hidden !p-0">
    <div class="flex aspect-video items-center justify-center overflow-hidden bg-zinc-100 dark:bg-zinc-800">
        @if ($previewUrl)
            <img src="{{ $previewUrl }}" alt="" class="size-full object-cover" loading="lazy" />
        @else
            <flux:icon.image class="size-10 text-zinc-400 dark:text-zinc-500" />
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-3 p-4">
        <div class="min-w-0">
            <flux:heading size="sm" class="truncate">{{ $item->name }}</flux:heading>
            <flux:text size="xs" variant="subtle" class="truncate">{{ $item->file_name }}</flux:text>
        </div>

        <div class="flex flex-wrap gap-1.5">
            <flux:badge size="sm">{{ $item->type }}</flux:badge>
            <flux:badge size="sm" color="zinc">{{ $item->size_label }}</flux:badge>
        </div>

        <div class="mt-auto flex items-center justify-between gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-700">
            <code class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $item->uuid }}</code>
            @if ($mediaUrl)
                <flux:button :href="$mediaUrl" target="_blank" size="xs" variant="ghost" icon="arrow-up-right" aria-label="Open {{ $item->name }}" />
            @endif
        </div>
    </div>
</flux:card>
