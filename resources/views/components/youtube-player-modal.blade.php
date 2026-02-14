<div x-data="youtube_player" x-on:open-youtube-player.window="openVideo($event.detail.url)">
    <flux:modal name="youtube-player" class="min-w-[50rem]" x-on:close="videoId = ''">
        <div class="space-y-4">
            <flux:heading size="lg">Video Preview</flux:heading>
            <template x-if="videoId">
                <div class="aspect-video">
                    <iframe
                        x-bind:src="'https://www.youtube.com/embed/' + videoId + '?autoplay=1'"
                        class="h-full w-full rounded-lg"
                        allow="autoplay; encrypted-media"
                        allowfullscreen
                    ></iframe>
                </div>
            </template>
        </div>
    </flux:modal>
</div>
