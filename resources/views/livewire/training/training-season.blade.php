<div class="space-y-6">
    <div class="mb-8">
        <flux:heading size="xl" class="mb-2">{{ $season->name() }}</flux:heading>
        <flux:subheading>
            {{ count($season->getChildren()) }} blocks •
            {{ collect($season->getChildren())->sum(fn($block) => count($block->getChildren())) }} weeks total
        </flux:subheading>
    </div>

    <div class="grid gap-6">
        @foreach ($season->getChildren() as $block)
            <livewire:training.training-block :block="$block" :key="'block-'.($block->uuid)" />
        @endforeach
    </div>
</div>
