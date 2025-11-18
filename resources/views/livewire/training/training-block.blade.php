<flux:card class="p-0">
    {{-- Block Header --}}
    <div class="px-6 py-4 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">Block {{ $block->sequence + 1 }}</flux:heading>
                <flux:subheading class="mt-1">
                    {{ count($block->children) }} weeks
                </flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:button size="sm" variant="ghost" icon="pencil">Edit</flux:button>
                <flux:button size="sm" variant="ghost" icon="trash">Delete</flux:button>
            </div>
        </div>
    </div>

    {{-- Weeks List --}}
    <div class="p-6 space-y-6">
        @forelse ($block->children as $week)
            <livewire:training.training-week :week="$week" :key="'week-'.($week->id ?? uniqid())" />
        @empty
            <p class="text-zinc-500">No weeks in this block</p>
        @endforelse
    </div>
</flux:card>
