<div>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Create Season (Itinerary)</h1>
        </div>

        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-2">
                <livewire:planner.itinerary-config />
            </div>

            <div class="col-span-10">
                <flux:card class="h-full">
                    <flux:heading size="lg">Weekly Itinerary</flux:heading>

                    @if ($this->activeBlock)
                        <div wire:key="tabs-container-{{ count($this->blocks) }}">
                            <flux:tabs variant="segmented" class="mt-4 mb-4">
                                @foreach ($this->blocks as $blockIndex => $block)
                                    <flux:tab wire:click="setActiveBlock({{ $blockIndex }})"
                                        :selected="$activeBlockIndex === $blockIndex">
                                        Block {{ $blockIndex + 1 }}
                                    </flux:tab>
                                @endforeach
                            </flux:tabs>
                        </div>

                        <livewire:planner.itinerary-grid :block="$this->activeBlock" :key="'block-' . $activeBlockIndex . '-weeks-' . $config->weeksPerBlock" />
                    @endif
                </flux:card>
            </div>
        </div>
    </div>
</div>
