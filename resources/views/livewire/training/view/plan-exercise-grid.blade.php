<div>
    <x-cms::section :title="($groupLabel ? $groupLabel . ' - ' : '') . $exerciseName">
        @if ($this->isDisabled)
            <div class="rounded-lg border border-zinc-700/50 bg-zinc-800/30 p-6 text-center space-y-3">
                @if ($this->userId !== null && $this->isDisabledByDefault)
                    <flux:text class="text-zinc-400">{{ __('This exercise has been disabled for all athletes by default.') }}</flux:text>
                @elseif ($this->userId !== null)
                    <flux:text class="text-zinc-400">{{ __('This exercise has been disabled for this athlete.') }}</flux:text>
                @else
                    <flux:text class="text-zinc-400">{{ __('This exercise has been disabled for all athletes by default.') }}</flux:text>
                @endif
                <div>
                    <flux:button variant="primary" size="sm" wire:click="toggleDisabled">{{ __('Enable') }}</flux:button>
                </div>
            </div>
        @elseif ($this->missingBlockGoal)
            <div class="rounded-lg border border-zinc-700/50 bg-zinc-800/30 p-6 text-center">
                <flux:text class="text-zinc-400">{{ __('Please add this program to a block with a target goal (%) for 1RM progression.') }}</flux:text>
            </div>
        @else
            <div wire:key="grid-content-{{ $exerciseId }}-{{ $valueDisplayMode }}-{{ $this->configFingerprint }}-{{ $gridRenderVersion }}">
                <div class="flex items-start justify-between !-mt-2">
                    <div class="space-y-2 mb-3">
                        @if (! empty($exerciseBadges))
                            <div class="flex flex-wrap gap-1 mt-3">
                                @foreach ($exerciseBadges as $badge)
                                    <flux:badge size="sm" color="{{ $badge['color'] ?? '' }}">{{ $badge['label'] }}</flux:badge>
                                @endforeach
                            </div>
                        @endif

                        @if ($valueDisplayMode !== 'actual')
                            @if (! empty($this->settingBadges))
                                <div class="flex flex-wrap gap-1 {{ empty($exerciseBadges) ? 'mt-3' : '' }}">
                                    @if ($this->groupingBadge['overridden'])
                                        <flux:badge size="sm" color="{{ $this->groupingBadge['color'] }}" class="cursor-pointer" wire:click="openGroupingForm">{{ __($this->groupingBadge['label']) }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" class="cursor-pointer" wire:click="openGroupingForm">{{ __($this->groupingBadge['label']) }}</flux:badge>
                                    @endif
                                    @foreach ($this->settingBadges as $badge)
                                        @if ($badge['overridden'])
                                            <flux:badge size="sm" color="green" class="cursor-pointer" wire:click="openSettingsForm('{{ $badge['modalField'] }}')">{{ $badge['label'] }}*</flux:badge>
                                        @else
                                            <flux:badge size="sm" class="cursor-pointer" wire:click="openSettingsForm('{{ $badge['modalField'] }}')">{{ $badge['label'] }}</flux:badge>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div class="flex flex-wrap gap-1 {{ empty($exerciseBadges) ? 'mt-3' : '' }}">
                                    @if ($this->groupingBadge['overridden'])
                                        <flux:badge size="sm" color="{{ $this->groupingBadge['color'] }}" class="cursor-pointer" wire:click="openGroupingForm">{{ __($this->groupingBadge['label']) }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" class="cursor-pointer" wire:click="openGroupingForm">{{ __($this->groupingBadge['label']) }}</flux:badge>
                                    @endif
                                </div>
                            @endif
                        @endif

                        @if (! $this->requiresMeasuredData || $this->hasMeasuredData)
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
                                    <span class="text-orange-400">{{ $summary['starting1RM'] ?? '' }}kg</span>
                                    <span class="text-zinc-500">({{ $modifierLabel }})</span>
                                    <span class="text-zinc-500 dark:text-zinc-400">&rarr;</span>
                                    <span class="text-emerald-400">{{ $summary['target1RM'] ?? '' }}kg</span>
                                    <span class="text-zinc-500">({{ $goalLabel }})</span>
                                </div>
                            @endif
                        @endif
                    </div>

                    <flux:dropdown>
                        <flux:button variant="ghost" size="sm" icon="ellipsis" class="!p-1" />
                        <flux:menu>
                            <flux:menu.item icon="pencil" wire:click="openSettingsForm">{{ __('Edit') }}</flux:menu.item>
                            <flux:menu.item icon="rotate-ccw" wire:click="resetOverrides">{{ __('Reset') }}</flux:menu.item>
                            <flux:menu.item icon="eye-off" wire:click="toggleDisabled">{{ __('Disable') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>

                @if ($valueDisplayMode === 'actual')
                    <x-training.plan-actual-grid :table="$this->planActualGridTable" />
                @else
                    <div x-on:grid-setting-click="$wire.openSettingsForm($event.detail.field)">
                        <x-training.plan-grid
                            :grid="$this->displayGrid"
                            :name="$exerciseName"
                            :showHeader="false"
                            :settingClickable="true"
                            :collapseWeeks="false"
                            :copyMenuOptions="$this->copyMenuOptions"
                            :previewMenuOptions="$this->previewMenuOptions"
                            :showPreview="$this->userId !== null"
                        />
                    </div>
                @endif
            </div>
        @endif
    </x-cms::section>
</div>
