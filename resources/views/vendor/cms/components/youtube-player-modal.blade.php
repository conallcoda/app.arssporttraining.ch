<div x-data="youtube_player" x-on:open-youtube-player.window="openVideo($event.detail.url)">
    <flux:modal
        name="youtube-player"
        class="w-[calc(100vw-1.5rem)] max-w-3xl sm:w-full"
        x-on:close="videoId = ''; videoUrl = ''"
    >
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Video Preview') }}</flux:heading>

            <template x-if="videoId">
                <div class="space-y-4">
                    <div class="aspect-video w-full overflow-hidden rounded-lg bg-black">
                        <iframe
                            x-bind:src="'https://www.youtube.com/embed/' + videoId + '?autoplay=1'"
                            class="h-full w-full"
                            allow="autoplay; encrypted-media"
                            allowfullscreen
                        ></iframe>
                    </div>

                    <div class="flex justify-end">
                        <flux:button type="button" x-on:click="openOnYouTube()">
                            {{ __('Watch on YouTube') }}
                            <flux:icon.arrow-top-right-on-square class="size-4" />
                        </flux:button>
                    </div>
                </div>
            </template>
        </div>
    </flux:modal>
</div>
