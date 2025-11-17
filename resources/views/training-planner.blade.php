<div>
    @if ($season)
        <livewire:training.training-season :season="$season" />
    @else
        {{-- Empty State --}}
        <div class="flex flex-col items-center justify-center py-24">
            <x-lucide-clipboard class="w-24 h-24 text-zinc-300 dark:text-zinc-600 mb-6" />
            <flux:heading size="xl" class="mb-2">No Training Plan Found</flux:heading>
            <flux:subheading class="mb-6">Create your first training season to get started</flux:subheading>
            <flux:button variant="primary" icon="plus">Create Season</flux:button>
        </div>
    @endif
</div>
