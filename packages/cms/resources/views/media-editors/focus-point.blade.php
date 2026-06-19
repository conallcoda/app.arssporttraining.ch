@php
    $previewUrl = $context['previewUrl'] ?? null;
@endphp

<div
    class="space-y-4"
    x-data="{ x: $wire.entangle('mediaEditorState.x').live, y: $wire.entangle('mediaEditorState.y').live }"
>
    @if ($previewUrl)
        <div class="relative mx-auto w-fit max-w-full">
            <img
                x-ref="image"
                src="{{ $previewUrl }}"
                alt="Preview"
                class="block max-h-[50vh] max-w-full object-contain select-none"
                @click="
                    const rect = $refs.image.getBoundingClientRect();
                    x = Math.min(1, Math.max(0, ($event.clientX - rect.left) / rect.width));
                    y = Math.min(1, Math.max(0, ($event.clientY - rect.top) / rect.height));
                "
            />

            <div
                class="pointer-events-none absolute z-10 h-6 w-6 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-zinc-900/55 shadow-lg"
                :style="`left:${x * 100}%; top:${y * 100}%`"
            >
                <div class="absolute left-1/2 top-1/2 h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white"></div>
            </div>
        </div>
    @endif
</div>
