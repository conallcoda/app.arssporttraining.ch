<flux:main>
    <div class="grid grid-cols-[35%_65%] gap-8">
        <div class="space-y-4">
            <flux:heading size="lg">Exercise Creator</flux:heading>
            <form class="space-y-4">
                @foreach ($this->fieldsets as $item)
                    @if ($item instanceof \App\Cms\Form\FormFieldsetGroup)
                        <x-cms.form.fieldset-tabs :group="$item" />
                    @else
                        <x-cms.form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="true" />
                    @endif
                @endforeach
            </form>
        </div>

        <div class="space-y-4">
            <div class="flex items-center gap-2">
                <flux:button size="sm" wire:click="$set('activeTab', 'preview')"
                    :variant="$activeTab === 'preview' ? 'primary' : 'ghost'">
                    Preview
                </flux:button>
                <flux:button size="sm" wire:click="$set('activeTab', 'data')"
                    :variant="$activeTab === 'data' ? 'primary' : 'ghost'">
                    Data
                </flux:button>
            </div>

            @if ($activeTab === 'data')
                <pre class="rounded-lg bg-zinc-100 p-4 text-sm dark:bg-zinc-800">{{ json_encode($data, JSON_PRETTY_PRINT) }}</pre>
            @else
                @php
                    $grid = $this->previewGrid;
                    $showMeasuredInputs =
                        in_array('weight', $data['settings'] ?? []) &&
                        ($data['weight']['mode'] ?? 'manual') === 'automatic';
                @endphp

                @if (count($grid->rows) === 0)
                    <div class="text-center text-zinc-500 dark:text-zinc-400 py-8">
                        Select settings to see preview.
                    </div>
                @else
                    @if ($showMeasuredInputs)
                        <x-section title="Target" class="w-fit">
                            <div class="grid grid-cols-[8rem_8rem_6rem] gap-x-3 gap-y-3 items-end">
                                <flux:field>
                                    <flux:label>Measured Reps</flux:label>
                                    <flux:input.group>
                                        <flux:input wire:model.live.debounce.500ms="measuredReps" type="number"
                                            min="1" max="15" step="1" />
                                        <flux:input.group.suffix>rep(s)</flux:input.group.suffix>
                                    </flux:input.group>
                                </flux:field>

                                <flux:field>
                                    <flux:label>Measured Weight</flux:label>
                                    <flux:input.group>
                                        <flux:input wire:model.live.debounce.500ms="measuredWeight" type="number"
                                            min="0" step="0.5" />
                                        <flux:input.group.suffix>kg</flux:input.group.suffix>
                                    </flux:input.group>
                                </flux:field>

                                <flux:field>
                                    <flux:label>Starting 1RM</flux:label>
                                    <div
                                        class="h-10 flex items-center justify-center rounded-lg bg-amber-500/15 text-amber-400 font-medium text-sm">
                                        {{ $grid->summary ? $grid->summary['starting1RM'] . 'kg' : 'N/A' }}
                                    </div>
                                </flux:field>

                                <flux:field class="col-span-2">
                                    <flux:label>Target Goal</flux:label>
                                    <flux:input.group>
                                        <flux:input wire:model.live.debounce.500ms="targetGoal" type="number"
                                            min="0" max="999" step="1" />
                                        <flux:input.group.suffix>%</flux:input.group.suffix>
                                    </flux:input.group>
                                </flux:field>

                                <flux:field>
                                    <flux:label>Target 1RM</flux:label>
                                    <div
                                        class="h-10 flex items-center justify-center rounded-lg bg-green-500/15 text-green-400 font-medium text-sm">
                                        {{ $grid->summary ? $grid->summary['target1RM'] . 'kg' : 'N/A' }}
                                    </div>
                                </flux:field>
                            </div>
                        </x-section>
                    @endif

                    <flux:heading size="lg">{{ $data['name'] ?? 'Untitled' }}</flux:heading>

                    <div class="overflow-x-auto text-sm">
                        <table class="border-collapse border border-zinc-300 dark:border-zinc-600 table-fixed">
                            <thead>
                                <tr class="bg-zinc-100 dark:bg-zinc-800">
                                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-14">Week</th>
                                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2"></th>
                                    @for ($i = 0; $i < $grid->setCount; $i++)
                                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16">
                                            {{ $grid->setLabel }} {{ $i + 1 }}</th>
                                    @endfor
                                    @foreach ($grid->weekColumns as $weekCol)
                                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16">
                                            {{ $weekCol->label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @for ($week = 0; $week < $grid->weekCount; $week++)
                                    @foreach ($grid->rows as $rowIdx => $row)
                                        <tr>
                                            @if ($rowIdx === 0)
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                                    rowspan="{{ count($grid->rows) }}">
                                                    TW{{ $week + 1 }}
                                                </td>
                                            @endif
                                            <td
                                                class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap {{ $row->color }}">
                                                {{ $row->label }}
                                            </td>
                                            @for ($set = 0; $set < $grid->setCount; $set++)
                                                <td
                                                    class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $row->color }}">
                                                    {{ $row->cells[$week][$set] ?? '-' }}
                                                </td>
                                            @endfor
                                            @if ($rowIdx === 0)
                                                @foreach ($grid->weekColumns as $weekCol)
                                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCol->color }}"
                                                        rowspan="{{ count($grid->rows) }}">
                                                        {{ $weekCol->cells[$week] ?? '-' }}
                                                    </td>
                                                @endforeach
                                            @endif
                                        </tr>
                                    @endforeach
                                @endfor
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>
</flux:main>
