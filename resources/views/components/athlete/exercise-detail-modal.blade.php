<flux:modal name="exercise-detail" variant="bare" class="w-screen h-screen max-w-none max-h-none m-0 rounded-none bg-white dark:bg-zinc-900 overflow-hidden flex flex-col">
    @if ($this->exerciseDetail)
        <div class="flex-1 overflow-y-auto p-6 space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="lg">{{ $this->exerciseDetail['name'] }}</flux:heading>
                    <flux:text class="text-sm">{{ $this->exerciseDetail['programName'] }}</flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-2 -m-2 text-zinc-400 hover:text-zinc-900 dark:hover:text-white">
                        <flux:icon.x class="size-8" />
                    </button>
                </flux:modal.close>
            </div>

            @if ($this->exerciseDetail['category'])
                <div>
                    <div class="text-xs font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Category</div>
                    <div class="mt-1">
                        <flux:badge size="sm">{{ $this->exerciseDetail['category'] }}</flux:badge>
                    </div>
                </div>
            @endif

            @if (!empty($this->exerciseDetail['equipment']))
                <div>
                    <div class="text-xs font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Equipment</div>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach ($this->exerciseDetail['equipment'] as $item)
                            <flux:badge size="sm" variant="outline">{{ $item }}</flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (!empty($this->exerciseDetail['modifiers']))
                <div>
                    <div class="text-xs font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Modifiers</div>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach ($this->exerciseDetail['modifiers'] as $item)
                            <flux:badge size="sm" variant="outline">{{ $item }}</flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (!empty($this->exerciseDetail['badges']))
                <div>
                    <div class="text-xs font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Settings</div>
                    <div class="mt-1 flex flex-wrap gap-2">
                        @foreach ($this->exerciseDetail['badges'] as $badge)
                            <flux:badge size="sm" color="blue">{{ $badge['label'] }}</flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($this->exerciseDetail['videoUrl'])
                <div>
                    <div class="text-xs font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Video</div>
                    <a href="{{ $this->exerciseDetail['videoUrl'] }}" target="_blank" rel="noopener noreferrer"
                       class="mt-1 inline-block text-sm text-blue-600 dark:text-blue-400 hover:underline truncate max-w-full">
                        {{ $this->exerciseDetail['videoUrl'] }}
                    </a>
                </div>
            @endif

            @if ($this->exerciseDetail['instructions'])
                <div>
                    <div class="text-xs font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Instructions</div>
                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300 whitespace-pre-line">{{ $this->exerciseDetail['instructions'] }}</div>
                </div>
            @endif
        </div>

        <div class="shrink-0 flex items-center gap-2 p-4 border-t border-zinc-200 dark:border-zinc-700">
            @if ($this->previousExercise)
                <flux:button
                    wire:click="navigateExercise(-1)"
                    class="flex-1 truncate py-3!"
                    style="{{ \Coda\Cms\Support\ColorPalette::solid($this->previousExercise['color']) }}"
                >
                    &lt; {{ $this->previousExercise['name'] }}
                </flux:button>
            @else
                <div class="flex-1"></div>
            @endif

            @if ($this->nextExercise)
                <flux:button
                    wire:click="navigateExercise(1)"
                    class="shrink-0 py-3!"
                    style="background-color: var(--color-green-500); color: white;"
                >
                    Exercise Complete
                </flux:button>
            @else
                <flux:button
                    wire:click="navigateExercise(1)"
                    class="shrink-0 py-3!"
                    style="background-color: var(--color-green-500); color: white;"
                >
                    Exercise Complete
                </flux:button>
            @endif

            @if ($this->nextExercise)
                <flux:button
                    wire:click="navigateExercise(1)"
                    class="flex-1 truncate py-3!"
                    style="{{ \Coda\Cms\Support\ColorPalette::solid($this->nextExercise['color']) }}"
                >
                    {{ $this->nextExercise['name'] }} &gt;
                </flux:button>
            @else
                <div class="flex-1"></div>
            @endif
        </div>
    @endif
</flux:modal>
