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
                    @if($selectedWeek)
                        <div class="bg-white rounded-lg shadow p-6">
                            <livewire:training.training-week :week="$selectedWeek" :key="'week-'.($selectedWeek->getIdentity()?->id ?? uniqid())" />
                        </div>
                    @else
                        <div class="bg-white rounded-lg shadow p-6 flex items-center justify-center h-64">
                            <p class="text-gray-500">Select a week from the tree to view details</p>
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
