<div @keydown.window.prevent.meta.s="$wire.saveChanges()" @keydown.window.prevent.ctrl.s="$wire.saveChanges()">
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Training Planner</h1>
            <div class="flex gap-2">
                <flux:button wire:click="openHistoryModal" variant="ghost" icon="clock">
                    History
                </flux:button>
                <flux:button wire:click="revertChanges" variant="ghost" :disabled="!$this->hasChanges">
                    Revert Changes
                </flux:button>
                <flux:button wire:click="saveChanges" :disabled="!$this->hasChanges">
                    Save Changes
                </flux:button>
            </div>
        </div>

        @if ($root)
            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-3">
                    <div class="bg-white rounded-lg shadow p-6" x-data="navigation_tree">
                        @if ($this->navigationTree)
                            @include('components.tree-node', [
                                'node' => $this->navigationTree,
                                'level' => 0,
                                'selectedUuid' => $selectedPeriodUuid,
                                'actions' => $this->navigationActions,
                            ])
                        @endif
                    </div>
                </div>

                <div class="col-span-9">
                    @if ($selectedPeriod && $selectedPeriodType)
                        <div class="bg-white rounded-lg shadow p-6">
                            @if ($selectedPeriodType === 'season')
                                <livewire:training.training-season :season="$selectedPeriod" :key="'season-' . $selectedPeriod->uuid . '-' . $lastChangeTimestamp" />
                            @elseif($selectedPeriodType === 'block')
                                <livewire:training.training-block :block="$selectedPeriod" :key="'block-' . $selectedPeriod->uuid . '-' . $lastChangeTimestamp" />
                            @elseif($selectedPeriodType === 'week')
                                <livewire:training.training-week :week="$selectedPeriod" :key="'week-' . $selectedPeriod->uuid . '-' . $lastChangeTimestamp" />
                            @endif
                        </div>
                    @else
                        <div class="bg-white rounded-lg shadow p-6 flex items-center justify-center h-64">
                            <p class="text-gray-500">Select a period from the tree to view details</p>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-yellow-800">No training root found. Please create one first.</p>
            </div>
        @endif
    </div>

    <flux:modal name="history" wire:model="showHistoryModal" variant="flyout">
        <flux:modal.trigger>
            <div></div>
        </flux:modal.trigger>

        <form class="space-y-6">
            <div>
                <flux:heading size="lg">Event History</flux:heading>
                <flux:subheading>View all actions performed on the training plan</flux:subheading>
            </div>

            <div class="space-y-2">
                @forelse($tree->getEvents() as $index => $event)
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 mt-0.5">
                                <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-xs font-medium text-blue-700">
                                    {{ count($tree->getEvents()) - $index }}
                                </div>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ $event->label() }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500">
                        <p>No events yet</p>
                    </div>
                @endforelse
            </div>

            <flux:button type="button" wire:click="closeHistoryModal">Close</flux:button>
        </form>
    </flux:modal>
</div>
