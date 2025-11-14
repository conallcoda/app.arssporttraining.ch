<div class="space-y-6">
    {{-- Season Header --}}
    <div class="mb-8">
        <flux:heading size="xl" class="mb-2">{{ $season->name }}</flux:heading>
        <flux:subheading>
            {{ $season->children->count() }} blocks •
            {{ $season->children->sum(fn($block) => $block->children->count()) }} weeks total
        </flux:subheading>
    </div>

    {{-- Training Blocks --}}
    <div class="grid gap-6">
        @foreach ($season->children as $block)
            <livewire:training.training-block :block="$block" :key="'block-'.$block->id" />
        @endforeach

        {{-- Add Block Card --}}
        <flux:card
            class="hover:shadow-md transition-shadow cursor-pointer border-2 border-dashed border-zinc-300 dark:border-zinc-600">
            <div class="flex flex-col items-center justify-center text-center py-12">
                <x-lucide-plus class="w-12 h-12 text-zinc-400 mb-3" />
                <flux:heading size="lg" class="text-zinc-500 mb-1">Add Block</flux:heading>
                <flux:subheading class="text-zinc-400">Create a new training block</flux:subheading>
            </div>
        </flux:card>
    </div>
</div>
