@props(['active' => null])

<div class="athlete-toolbar sticky top-0 z-20 md:mt-20 bg-zinc-100/95 px-0 py-0 backdrop-blur dark:bg-zinc-800/95">
    <div class="w-full md:mx-auto md:max-w-[900px] border-b border-zinc-200 px-0 dark:border-zinc-700">
        @if (trim((string) $slot) !== '')
            <div class="flex flex-col gap-0">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
