<div>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Create Block</h1>
        </div>

        <div class="grid grid-cols-12 gap-4">
            <x-resizable-card title="Training Schedule" storage-key="block-creator-schedule">
                <livewire:planner.schedule-grid :block="$this->tree->root" :key="'block-weeks-5'" />
            </x-resizable-card>
        </div>
    </div>
</div>
