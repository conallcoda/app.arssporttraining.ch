<div class="flex flex-col h-screen">
    <div class="flex items-center gap-3 px-4 py-2 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
        <flux:button
            variant="subtle"
            size="sm"
            icon="arrow-left"
            :href="$url"
        >
            {{ __('Back to site') }}
        </flux:button>

        <div class="flex items-center gap-1">
            @if ($device === 'mobile')
                <flux:button variant="primary" size="sm" square icon="smartphone" wire:click="$set('device', 'mobile')" />
            @else
                <flux:button variant="subtle" size="sm" square icon="smartphone" wire:click="$set('device', 'mobile')" />
            @endif

            @if ($device === 'desktop')
                <flux:button variant="primary" size="sm" square icon="monitor" wire:click="$set('device', 'desktop')" />
            @else
                <flux:button variant="subtle" size="sm" square icon="monitor" wire:click="$set('device', 'desktop')" />
            @endif
        </div>

        <div class="flex-1">
            <form wire:submit="$refresh">
                <flux:input
                    wire:model="url"
                    placeholder="URL to preview"
                    size="sm"
                    icon="globe"
                />
            </form>
        </div>

        @if (config('cms.user_switching'))
            <livewire:cms.user-switcher />
        @endif

        <x-cms::theme-toggle />
    </div>

    <div class="flex-1 flex items-start justify-center pt-6 pb-6 overflow-y-auto">
        @if ($device === 'mobile')
            <div class="relative shrink-0" style="width: 403px; height: 840px;">
                <div class="rounded-[3rem] border-[14px] border-zinc-800 dark:border-zinc-600 bg-zinc-800 dark:bg-zinc-600 shadow-2xl overflow-hidden h-full">
                    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[120px] h-[30px] bg-zinc-800 dark:bg-zinc-600 rounded-b-2xl z-10"></div>

                    <iframe
                        src="{{ $this->iframeUrl() }}"
                        class="bg-white"
                        style="width: 375px; height: 812px;"
                    ></iframe>
                </div>
            </div>
        @else
            <div class="w-full h-full px-6">
                <iframe
                    src="{{ $this->iframeUrl() }}"
                    class="w-full h-full bg-white rounded-lg border border-zinc-200 dark:border-zinc-700"
                ></iframe>
            </div>
        @endif
    </div>
</div>
