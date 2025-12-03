<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Progression Example</h1>
    </div>

    @if ($this->tree && $this->exercise)
        <div class="mb-4 text-xs text-zinc-500">
            Rows: {{ count($this->exerciseRows) }} | Sets: {{ $this->setCount }}
        </div>
        <x-progression-table
            :title="$this->exercise->name . ' (Gym 1A)'"
            :rows="$this->exerciseRows"
            :set-count="$this->setCount"
            :exercise-id="$this->exercise->id"
            :progression="$this->exerciseProgression"
            :group-by-week="true"
        />
    @else
        <div class="text-center py-8 text-zinc-500">
            Loading...
        </div>
    @endif
</div>
