<div class="space-y-6">
    <div class="flex gap-6">
        <x-section :title="__('General')" class="flex-1">
            @foreach ($this->fieldsets as $item)
                <x-cms::form.fieldset
                    :fieldset="$item"
                    :prefix="$item->prefix ?? 'data'"
                    :showLegend="false"
                />
            @endforeach

            <div class="grid grid-cols-2 gap-4">
                @foreach ($this->warmProgramFieldsets as $item)
                    <x-cms::form.fieldset
                        :fieldset="$item"
                        :prefix="$item->prefix ?? 'data'"
                        :showLegend="false"
                    />
                @endforeach
            </div>
        </x-section>

        @if ($showWeeksInput)
            <x-section :title="__('Settings')" class="w-64 shrink-0">
                <flux:field>
                    <flux:label>{{ __('Weeks') }}</flux:label>
                    <flux:input wire:model.live="weeks" type="number" min="1" max="52" step="1" />
                </flux:field>
            </x-section>
        @endif
    </div>

    @if ($this->exercises->isNotEmpty())
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" wire:key="grids-{{ $this->exercises->pluck('id')->implode('-') }}">
            @foreach ($this->exercises as $exercise)
                <div wire:key="grid-{{ $exercise->id }}-{{ $userId ?? 'default' }}" class="min-w-0">
                    <livewire:training.view.plan-exercise-grid
                        :key="'grid-' . $exercise->id . '-' . $weeks . '-' . ($userId ?? 'default')"
                        :exercisePlanId="$planId"
                        :planType="$planType"
                        :exerciseId="$exercise->id"
                        :userId="$userId"
                        :disabled="false"
                        :weeks="$weeks"
                        :sessionsPerWeek="$sessionsPerWeek"
                        :planMeasuredReps="$planMeasuredReps"
                        :planMeasuredWeight="$planMeasuredWeight"
                        :planTargetGoal="$planTargetGoal"
                    />
                </div>
            @endforeach
        </div>

        <livewire:training.view.plan-exercise-settings-form />
    @else
        <flux:text class="text-zinc-500">{{ __('No exercises in this program. Add exercises above to see the training grids.') }}</flux:text>
    @endif
</div>
