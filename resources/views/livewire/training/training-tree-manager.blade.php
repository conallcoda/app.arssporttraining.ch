<div class="space-y-4">
    <div class="flex justify-between items-center">
        <h2 class="text-xl font-bold">Training Seasons</h2>
        <x-filament::button wire:click="createSeason" icon="lucide-plus">
            New Season
        </x-filament::button>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        @if(count($seasons) === 0)
            <div class="text-center py-8 text-gray-500">
                No training seasons yet. Create one to get started.
            </div>
        @else
            <div class="space-y-2">
                @foreach($seasons as $season)
                    @include('livewire.training.partials.tree-node', [
                        'node' => $season,
                        'level' => 0,
                        'nodeType' => 'season'
                    ])
                @endforeach
            </div>
        @endif
    </div>

    @if($deletingNode)
        <x-filament::modal id="delete-confirmation" wire:model="deletingNode">
            <x-slot name="heading">
                Confirm Deletion
            </x-slot>

            <p>Are you sure you want to delete this item and all its children?</p>

            <x-slot name="footer">
                <x-filament::button color="danger" wire:click="deleteNode({{ $deletingNode }}, '{{ $deletingNodeType ?? 'season' }}')">
                    Delete
                </x-filament::button>
                <x-filament::button color="gray" wire:click="cancelDelete">
                    Cancel
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endif
</div>
