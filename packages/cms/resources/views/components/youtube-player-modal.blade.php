<div x-data="youtube_player" x-on:open-youtube-player.window="openVideo($event.detail.url)">
    <flux:modal
        name="youtube-player"
        class="w-[calc(100vw-1.5rem)] max-w-3xl sm:w-full"
        x-on:close="resetPlayer()"
    >
        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <flux:heading size="lg">{{ __('Video Preview') }}</flux:heading>

                <flux:button.group>
                    <flux:button
                        type="button"
                        size="sm"
                        x-bind:variant="aspectMode === 'portrait' ? 'primary' : 'outline'"
                        x-on:click="setAspectMode('portrait')"
                    >
                        {{ __('Portrait') }}
                    </flux:button>

                    <flux:button
                        type="button"
                        size="sm"
                        x-bind:variant="aspectMode === 'landscape' ? 'primary' : 'outline'"
                        x-on:click="setAspectMode('landscape')"
                    >
                        {{ __('Landscape') }}
                    </flux:button>
                </flux:button.group>
            </div>

            <template x-if="videoId">
                <div class="flex justify-center">
                    <div
                        class="w-full overflow-hidden rounded-lg bg-black transition-all duration-200"
                        x-bind:class="frameClass()"
                    >
                        <iframe
                            x-bind:src="'https://www.youtube.com/embed/' + videoId + '?autoplay=1'"
                            class="h-full w-full"
                            allow="autoplay; encrypted-media"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>
            </template>
        </div>
    </flux:modal>
</div>
