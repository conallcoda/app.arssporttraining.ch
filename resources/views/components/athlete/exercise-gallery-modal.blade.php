<div
    x-data="{
        images: [],
        activeIndex: 0,
        touchStartX: null,
        open(images, startIndex = 0) {
            this.images = Array.isArray(images) ? images : [];
            this.activeIndex = Math.max(0, Math.min(startIndex, this.images.length - 1));

            if (! this.images.length) {
                return;
            }

            Flux.modal('exercise-gallery').show();
        },
        close() {
            this.images = [];
            this.activeIndex = 0;
            this.touchStartX = null;
        },
        previous() {
            if (this.activeIndex > 0) {
                this.activeIndex--;
            }
        },
        next() {
            if (this.activeIndex < this.images.length - 1) {
                this.activeIndex++;
            }
        },
        onTouchStart(event) {
            this.touchStartX = event.changedTouches[0]?.clientX ?? null;
        },
        onTouchEnd(event) {
            const touchEndX = event.changedTouches[0]?.clientX ?? null;

            if (this.touchStartX === null || touchEndX === null) {
                return;
            }

            const delta = touchEndX - this.touchStartX;

            if (Math.abs(delta) > 40) {
                if (delta > 0) {
                    this.previous();
                } else {
                    this.next();
                }
            }

            this.touchStartX = null;
        },
    }"
    x-on:open-exercise-gallery.window="open($event.detail.images ?? [], $event.detail.index ?? 0)"
>
    <flux:modal
        name="exercise-gallery"
        class="w-[calc(100vw-1.5rem)] max-w-4xl"
        x-on:close="close()"
    >
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('Exercise Gallery') }}</flux:heading>

                <template x-if="images.length > 1">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400" x-text="`${activeIndex + 1} / ${images.length}`"></div>
                </template>
            </div>

            <template x-if="images.length">
                <div class="space-y-4">
                    <div
                        class="relative overflow-hidden rounded-xl bg-black"
                        x-on:touchstart="onTouchStart($event)"
                        x-on:touchend="onTouchEnd($event)"
                    >
                        <div class="flex aspect-[4/5] items-center justify-center">
                            <img
                                x-bind:src="images[activeIndex]"
                                alt=""
                                class="h-full w-full object-contain"
                            >
                        </div>

                        <template x-if="images.length > 1">
                            <div class="pointer-events-none absolute inset-y-0 left-0 right-0 flex items-center justify-between px-3">
                                <button
                                    type="button"
                                    class="pointer-events-auto inline-flex size-10 items-center justify-center rounded-full bg-black/50 text-white transition hover:bg-black/65 disabled:cursor-not-allowed disabled:opacity-40"
                                    x-on:click="previous()"
                                    x-bind:disabled="activeIndex === 0"
                                >
                                    <flux:icon.chevron-left class="size-5" />
                                </button>

                                <button
                                    type="button"
                                    class="pointer-events-auto inline-flex size-10 items-center justify-center rounded-full bg-black/50 text-white transition hover:bg-black/65 disabled:cursor-not-allowed disabled:opacity-40"
                                    x-on:click="next()"
                                    x-bind:disabled="activeIndex === images.length - 1"
                                >
                                    <flux:icon.chevron-right class="size-5" />
                                </button>
                            </div>
                        </template>
                    </div>

                    <template x-if="images.length > 1">
                        <div class="flex gap-2 overflow-x-auto pb-1">
                            <template x-for="(image, index) in images" :key="`${image}-${index}`">
                                <button
                                    type="button"
                                    class="shrink-0 overflow-hidden rounded-lg border-2 transition"
                                    x-bind:class="index === activeIndex ? 'border-zinc-900 dark:border-white' : 'border-transparent opacity-70'"
                                    x-on:click="activeIndex = index"
                                >
                                    <img
                                        x-bind:src="image"
                                        alt=""
                                        class="size-14 object-cover"
                                    >
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </flux:modal>
</div>
