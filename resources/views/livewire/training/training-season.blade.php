<div class="space-y-6">
    <div class="mb-8">
        <flux:heading size="xl" class="mb-2">{{ $season->name() }}</flux:heading>
        <flux:subheading>
            {{ count($season->children) }} blocks •
            {{ collect($season->children)->sum(fn($block) => count($block->children)) }} weeks total
        </flux:subheading>
    </div>

    <div class="grid gap-6">
        @foreach ($season->children as $block)
            <livewire:training.training-block :block="$block" :key="'block-'.($block->uuid)" />
        @endforeach
    </div>
</div>
