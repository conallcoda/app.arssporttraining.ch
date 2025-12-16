<div>
    <div
        class="mb-4 sm:mb-6 bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div
            class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-center justify-between">
            <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Default Configuration</h3>
            <x-help-tooltip
                content="Set the target goal percentage and training strategy. Use Advanced settings for fine-tuned control over steps and rules." />
        </div>
        <div class="p-4">
            <div class="flex flex-wrap items-end gap-4">
                <flux:radio.group wire:model.live="targetGoal" label="Target Goal (%)" variant="segmented">
                    <flux:radio value="0" label="0%" />
                    <flux:radio value="2.5" label="2.5%" />
                    <flux:radio value="5" label="5%" />
                    <flux:radio value="7.5" label="7.5%" />
                    <flux:radio value="10" label="10%" />
                </flux:radio.group>

                <flux:radio.group wire:model.live="strategyConfig.set_paired_reps.startingReps" label="Starting Reps"
                    variant="segmented">
                    <flux:radio value="8" label="8" />
                    <flux:radio value="10" label="10" />
                    <flux:radio value="12" label="12" />
                    <flux:radio value="14" label="14" />
                    <flux:radio value="16" label="16" />
                </flux:radio.group>

                <flux:radio.group wire:model.live="strategyConfig.create_empty_block.sets" label="# Sets"
                    variant="segmented">
                    <flux:radio value="4" label="4" />
                    <flux:radio value="5" label="5" />
                    <flux:radio value="6" label="6" />
                </flux:radio.group>

                <div class="hidden">
                    <flux:select wire:model.live="selectedStrategy" label="Strategy">
                        @foreach ($this->getStrategies() as $key => $class)
                            <flux:select.option value="{{ $key }}">
                                {{ str_replace('_', ' ', ucwords($key, '_')) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:button wire:click="openAdvancedModal" icon="adjustments-horizontal">
                    Advanced
                </flux:button>
            </div>
        </div>
    </div>

    <flux:modal name="advanced-strategy-modal" wire:model="showAdvancedModal" variant="flyout" class="w-96">
        <form wire:submit="saveAdvancedConfig" class="space-y-6">
            <div>
                <flux:heading size="lg">Advanced Configuration</flux:heading>
                <flux:subheading>Configure strategy and rules parameters</flux:subheading>
            </div>

            <flux:tab.group>
                <flux:tabs variant="segmented">
                    <flux:tab name="steps">Steps</flux:tab>
                    <flux:tab name="rules">Rules</flux:tab>
                </flux:tabs>

                <flux:tab.panel name="steps">
                    <div class="space-y-4 pt-4">
                        @foreach ($this->getStrategyFormFields() as $actionType => $fieldOrFieldset)
                            <div wire:key="action-{{ $actionType }}">
                                @if ($fieldOrFieldset instanceof \App\Data\Form\FluxFieldset)
                                    <x-flux-fieldset :fieldset="$fieldOrFieldset" :prefix="'strategyConfig.' . $actionType" />
                                @elseif (is_array($fieldOrFieldset))
                                    @foreach ($fieldOrFieldset as $field)
                                        <x-flux-field :field="$field" :prefix="'strategyConfig.' . $actionType" />
                                    @endforeach
                                @endif
                            </div>
                        @endforeach
                    </div>
                </flux:tab.panel>

                <flux:tab.panel name="rules">
                    <div class="space-y-4 pt-4">
                        @foreach ($this->getInitialRules() as $ruleType => $ruleInfo)
                            <div wire:key="initial-rule-{{ $ruleType }}"
                                class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium">{{ $ruleInfo['title'] }}</span>
                                    <flux:switch wire:model.live="initialRulesEnabled.{{ $ruleType }}" />
                                </div>
                                @if ($ruleInfo['hasFields'] && ($initialRulesEnabled[$ruleType] ?? false))
                                    <div class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                                        <x-flux-fieldset :fieldset="$ruleInfo['fieldset']" :prefix="'initialRulesConfig.' . $ruleType" :showLegend="false" />
                                    </div>
                                @endif
                            </div>
                        @endforeach
                        @foreach ($this->getActionRules() as $ruleType => $ruleInfo)
                            <div wire:key="action-rule-{{ $ruleType }}"
                                class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium">{{ $ruleInfo['title'] }}</span>
                                    <flux:switch wire:model.live="actionRulesEnabled.{{ $ruleType }}" />
                                </div>
                                @if ($ruleInfo['hasFields'] && ($actionRulesEnabled[$ruleType] ?? false))
                                    <div class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                                        <x-flux-fieldset :fieldset="$ruleInfo['fieldset']" :prefix="'actionRulesConfig.' . $ruleType" :showLegend="false" />
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </flux:tab.panel>
            </flux:tab.group>

            <div class="flex gap-2 justify-end pt-4">
                <flux:button type="button" variant="ghost" wire:click="closeAdvancedModal">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
