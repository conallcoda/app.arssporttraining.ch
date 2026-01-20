<div
    x-data="block_creator_storage({ persistToStorage: false })"
    x-init="init()"
    @grid-refresh.window="saveToStorage($event.detail)"
    @tree-updated.window="saveToStorage($event.detail)"
>
    @if ($this->tree)
        <div class="p-6" x-show="initialized" x-cloak>
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold">Create Block</h1>
                <div class="flex items-center gap-2">
                    <span x-show="hasStoredData" x-cloak class="text-xs text-zinc-500">
                        Auto-saved
                    </span>
                    <flux:button x-show="hasStoredData" x-cloak size="sm" variant="ghost" @click="clearStorage()">
                        <x-lucide-trash-2 class="w-4 h-4 mr-1" />
                        Clear
                    </flux:button>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-4">
                <x-resizable-card title="Training Schedule" storage-key="block-creator-schedule" class="col-span-12">
                    <livewire:planner.schedule-grid :block="$this->tree->root" :key="'block-weeks-5'" />
                </x-resizable-card>
            </div>
        </div>
    @endif
</div>
