<div>
    <flux:tab.group>
        <div class="sticky top-0 z-10 bg-white px-3 pt-4 sm:px-6 sm:pt-6 dark:bg-zinc-900">
            <flux:tabs wire:model.live="tab">
                <flux:tab name="calculator">Calculator</flux:tab>
                <flux:tab name="documentation">Documentation</flux:tab>
            </flux:tabs>
        </div>

        <flux:tab.panel name="calculator" class="px-3 sm:px-6">
            <div class="pt-4 sm:pt-6">
                <div class="mb-4 sm:mb-6">
                    <h1 class="text-xl sm:text-2xl font-bold">Training Plan Calculator</h1>
                </div>

                <div class="mb-4 sm:mb-6 grid grid-cols-1 gap-4 sm:gap-6 text-sm md:grid-cols-2">
                    <div
                        class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                        <div
                            class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-center justify-between">
                            <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Simplified Exercise
                                Database</h3>
                            <x-help-tooltip
                                content="Contains exercise definitions with their modifiers. Click on modifier values to edit them inline." />
                        </div>
                        <div class="p-4 overflow-x-auto">
                            <table class="w-full border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">ID</th>
                                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Name</th>
                                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($exercises as $ex)
                                        <tr>
                                            <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">
                                                {{ $ex->id }}
                                            </td>
                                            <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">
                                                {{ $ex->name }}
                                            </td>
                                            <td class="border border-zinc-300 dark:border-zinc-600 w-20 h-10 p-0"
                                                x-data="editable_cell($wire, 'updateExerciseModifier', [{{ $ex->id }}], {{ $ex->modifier }}, '%')" @click="startEditing">
                                                <div x-show="!editing" class="px-3 py-2 cursor-pointer text-center"
                                                    x-text="value + '%'"></div>
                                                <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                                    @blur="save" @keydown="handleKeydown" type="number"
                                                    step="0.1" min="1"
                                                    class="w-full h-full text-center border border-black outline-none focus:border-black focus:ring-0" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div
                        class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                        <div
                            class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-center justify-between">
                            <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Simplified Athlete Database
                            </h3>
                            <x-help-tooltip
                                content="Contains athlete test data including reps, weight, and calculated 1RM. Click on reps or weight values to edit them inline." />
                        </div>
                        <div class="p-4 overflow-x-auto">
                            <table class="w-full border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">ID</th>
                                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Name</th>
                                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Test (Reps)
                                        </th>
                                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Test (Weight)
                                        </th>
                                        <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Test (1RM)
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($athletes as $ath)
                                        <tr>
                                            <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">
                                                {{ $ath->id }}
                                            </td>
                                            <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">
                                                {{ $ath->name }}
                                            </td>
                                            @foreach ($ath->tests as $testIndex => $test)
                                                <td class="border border-zinc-300 dark:border-zinc-600 w-20 h-10 p-0"
                                                    x-data="editable_cell($wire, 'updateAthleteTestReps', [{{ $ath->id }}, {{ $testIndex }}], {{ $test->reps }})" @click="startEditing">
                                                    <div x-show="!editing" class="px-3 py-2 cursor-pointer text-center"
                                                        x-text="value"></div>
                                                    <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                                        @blur="save" @keydown="handleKeydown" type="number"
                                                        step="1" min="1"
                                                        class="w-full h-full text-center border border-black outline-none focus:border-black focus:ring-0" />
                                                </td>
                                                <td class="border border-zinc-300 dark:border-zinc-600 w-24 h-10 p-0"
                                                    x-data="editable_cell($wire, 'updateAthleteTestWeight', [{{ $ath->id }}, {{ $testIndex }}], {{ $test->weight }}, ' kg')" @click="startEditing">
                                                    <div x-show="!editing" class="px-3 py-2 cursor-pointer text-center"
                                                        x-text="value + ' kg'"></div>
                                                    <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                                        @blur="save" @keydown="handleKeydown" type="number"
                                                        step="0.5" min="1"
                                                        class="w-full h-full text-center border border-black outline-none focus:border-black focus:ring-0" />
                                                </td>
                                                <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">
                                                    {{ number_format($test->oneRepMax, 1) }} kg</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div
                    class="mb-4 sm:mb-6 bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <div
                        class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-center justify-between">
                        <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Configuration</h3>
                        <x-help-tooltip
                            content="Select an athlete, exercise, target goal percentage, and training strategy. Use Advanced settings for fine-tuned control over steps and rules." />
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:flex lg:items-end">
                            <flux:select wire:model.live="selectedAthleteId" label="Athlete">
                                @foreach ($athletes as $ath)
                                    <flux:select.option value="{{ $ath->id }}">{{ $ath->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model.live="selectedExerciseId" label="Exercise">
                                @foreach ($exercises as $ex)
                                    <flux:select.option value="{{ $ex->id }}">{{ $ex->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model.live="targetGoal" type="number" label="Target Goal (%)"
                                step="0.1" />

                            <flux:select wire:model.live="selectedStrategy" label="Strategy">
                                @foreach ($this->getStrategies() as $key => $class)
                                    <flux:select.option value="{{ $key }}">
                                        {{ str_replace('_', ' ', ucwords($key, '_')) }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:button wire:click="openAdvancedModal" icon="adjustments-horizontal"
                                class="sm:col-span-2 lg:col-span-1">
                                Advanced
                            </flux:button>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-3 sm:flex sm:flex-wrap">
                            <x-stat-card label="Starting 1RM" :value="number_format($config->startingOneRepMax, 1) . ' kg'" />
                            <x-stat-card label="Target 1RM" :value="number_format($config->targetOneRepMax, 1) . ' kg'" />
                            <x-stat-card label="Target" :value="$config->target . '%'" />
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <div
                        class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-start gap-2 justify-between sm:items-center">
                        <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            <span class="sm:inline block">{{ $this->manager->exercise()->name }} Training Plan</span>
                            <span class="sm:inline block">for {{ $this->manager->athlete()->name }}</span>
                            <span
                                class="text-zinc-500 dark:text-zinc-400">({{ str_replace('_', ' ', ucwords($selectedStrategy, '_')) }})</span>
                        </h3>
                        <x-help-tooltip
                            content="Visual, step by step, example of how the system automatically generates training blocks based on the selected strategy and goals." />
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <div class="flex flex-wrap gap-4">
                            @foreach ($this->manager->results as $index => $result)
                                <x-exercise-block-grid :block="$result->current" :title="'Step ' . ($index + 1) . ': ' . $result->title()" :helpText="$result->helpText()"
                                    :highlightedCells="$result->getHighlightedCells()" />
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="documentation" class="px-3 sm:px-6">
            <div class="flex gap-8 pt-6">
                {{-- Sticky Sidebar TOC --}}
                @if (count($this->documentationToc) > 0)
                    <aside class="hidden w-64 shrink-0 lg:block">
                        <nav
                            class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <p class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Contents</p>
                            <ul class="space-y-1 text-sm">
                                @foreach ($this->documentationToc as $item)
                                    <li class="{{ $item['level'] === 3 ? 'ml-4' : '' }}">
                                        <a href="#{{ $item['slug'] }}"
                                            class="flex text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                                            <span
                                                class="mr-2 text-zinc-400 dark:text-zinc-500">{{ $item['number'] }}</span>
                                            <span>{{ $item['title'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    </aside>
                @endif

                {{-- Main Content --}}
                <div
                    class="prose prose-zinc dark:prose-invert min-w-0 max-w-none flex-1 prose-headings:scroll-mt-16 prose-h1:text-2xl prose-h2:text-xl prose-h3:text-lg prose-h3:font-semibold prose-h4:text-base prose-h4:font-medium prose-p:text-zinc-600 dark:prose-p:text-zinc-300 prose-a:text-accent prose-a:no-underline hover:prose-a:underline prose-table:w-full prose-table:text-sm prose-th:bg-zinc-100 prose-th:px-4 prose-th:py-2 prose-th:text-left prose-th:font-semibold dark:prose-th:bg-zinc-800 prose-td:border-t prose-td:border-zinc-200 prose-td:px-4 prose-td:py-2 dark:prose-td:border-zinc-700 prose-strong:text-zinc-900 dark:prose-strong:text-zinc-100 prose-hr:border-zinc-200 dark:prose-hr:border-zinc-700 pb-8">
                    {!! $this->documentationHtml !!}
                </div>
            </div>
        </flux:tab.panel>
    </flux:tab.group>

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
