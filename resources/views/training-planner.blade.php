<div>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Training Planner</h1>
        </div>

        @if($season)
            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-3">
                    <div class="bg-white rounded-lg shadow p-6">
                        @include('training-planner.tree-node', ['node' => $season, 'depth' => 0])
                    </div>
                </div>

                <div class="col-span-9">
                    @if($selectedPeriod && $selectedPeriodType)
                        <div class="bg-white rounded-lg shadow p-6">
                            @if($selectedPeriodType === 'season')
                                <livewire:training.training-season :season="$selectedPeriod" :key="'season-'.($selectedPeriod->uuid)" />
                            @elseif($selectedPeriodType === 'block')
                                <livewire:training.training-block :block="$selectedPeriod" :key="'block-'.($selectedPeriod->uuid)" />
                            @elseif($selectedPeriodType === 'week')
                                <livewire:training.training-week :week="$selectedPeriod" :key="'week-'.($selectedPeriod->uuid)" />
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
                <p class="text-yellow-800">No training season found. Please create one first.</p>
            </div>
        @endif
    </div>
</div>
