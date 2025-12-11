<div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    @if ($this->history)
        <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-start gap-2 justify-between sm:items-center">
            <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                <span class="sm:inline block">{{ $this->history->exercise()->name }} Training Plan</span>
                <span class="sm:inline block">for {{ $this->history->athlete()->name }}</span>
                <span class="text-zinc-500 dark:text-zinc-400">({{ str_replace('_', ' ', ucwords($strategy, '_')) }})</span>
            </h3>
            <x-help-tooltip content="Visual, step by step, example of how the system automatically generates training blocks based on the selected strategy and goals." />
        </div>
        <div class="p-4 overflow-x-auto">
            <div class="flex flex-wrap gap-4">
                @foreach ($this->history->results as $index => $result)
                    <x-exercise-block-grid
                        :block="$result->current"
                        :title="'Step ' . ($index + 1) . ': ' . $result->title()"
                        :helpText="$result->helpText()"
                        :highlightedCells="$result->getHighlightedCells()"
                    />
                @endforeach
            </div>
        </div>
    @else
        <div class="p-4 text-center text-zinc-500">
            Loading training plan...
        </div>
    @endif
</div>
