<div>
    <x-section :title="$exerciseName">
        <div class="flex items-start justify-between !-mt-2">
            <div class="space-y-1">
                @if ($this->previewGrid->summary)
                    @php
                        $summary = $this->previewGrid->summary;
                        $modifier = $summary['modifier'] ?? 100;
                        $modifierOffset = $modifier - 100;
                        $modifierLabel = ($modifierOffset >= 0 ? '+' : '') . $modifierOffset . '%';

                        $targetGoal = $summary['targetGoal'] ?? 0;
                        $goalLabel = ($targetGoal >= 0 ? '+' : '') . $targetGoal . '%';
                    @endphp
                    <div class="text-sm flex items-center gap-1">
                        <span class="text-emerald-400">{{ $summary['starting1RM'] ?? '' }}kg</span>
                        <span class="text-zinc-500">({{ $modifierLabel }})</span>
                        <span class="text-zinc-500 dark:text-zinc-400">&rarr;</span>
                        <span class="text-emerald-400">{{ $summary['target1RM'] ?? '' }}kg</span>
                        <span class="text-zinc-500">({{ $goalLabel }})</span>
                    </div>
                @endif

                @if (! empty($exerciseBadges))
                    <div class="flex flex-wrap gap-1">
                        @foreach ($exerciseBadges as $badge)
                            <flux:badge size="sm" color="{{ $badge['color'] ?? '' }}">{{ $badge['label'] }}</flux:badge>
                        @endforeach
                    </div>
                @endif
            </div>

            <flux:dropdown>
                <flux:button variant="ghost" size="sm" icon="ellipsis" class="!p-1" />
                <flux:menu>
                    <flux:menu.item icon="pencil" wire:click="openSettingsForm">Edit Settings</flux:menu.item>
                    <flux:menu.item icon="rotate-ccw" wire:click="resetOverrides">Reset Overrides</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>

        <x-training.exercise-grid
            :grid="$this->previewGrid"
            :name="$exerciseName"
            :showHeader="false"
        />
    </x-section>
</div>
