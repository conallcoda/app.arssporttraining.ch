<flux:main>
    <div class="grid grid-cols-[35%_65%] gap-8">
        <div class="space-y-4">
            <flux:heading size="lg">Exercise Creator</flux:heading>
            <form class="space-y-4">
                @foreach ($this->fieldsets as $fieldset)
                    <x-cms.form.fieldset
                        :fieldset="$fieldset"
                        :prefix="$fieldset->prefix ?? 'data'"
                        :showLegend="true"
                    />
                @endforeach
            </form>
        </div>

        <div class="space-y-4">
            <div class="flex items-center gap-2">
                <flux:button
                    size="sm"
                    wire:click="$set('activeTab', 'preview')"
                    :variant="$activeTab === 'preview' ? 'primary' : 'ghost'"
                >
                    Preview
                </flux:button>
                <flux:button
                    size="sm"
                    wire:click="$set('activeTab', 'data')"
                    :variant="$activeTab === 'data' ? 'primary' : 'ghost'"
                >
                    Data
                </flux:button>
            </div>

            @if ($activeTab === 'data')
                <pre class="rounded-lg bg-zinc-100 p-4 text-sm dark:bg-zinc-800">{{ json_encode($data, JSON_PRETTY_PRINT) }}</pre>
            @else
                @php
                    $grid = $this->previewGrid;
                @endphp

                @if (count($grid->rows) === 0)
                    <div class="text-center text-zinc-500 dark:text-zinc-400 py-8">
                        Select settings to see preview.
                    </div>
                @else
                    <flux:heading size="lg">{{ $data['name'] ?? 'Untitled' }}</flux:heading>
                    <div class="overflow-x-auto text-sm">
                        <table class="border-collapse border border-zinc-300 dark:border-zinc-600 table-fixed">
                            <thead>
                                <tr class="bg-zinc-100 dark:bg-zinc-800">
                                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-14">Week</th>
                                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16"></th>
                                    @for ($i = 0; $i < $grid->setCount; $i++)
                                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16">{{ $grid->setLabel }} {{ $i + 1 }}</th>
                                    @endfor
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
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium {{ $row->color }}">
                                                {{ $row->label }}
                                            </td>
                                            @for ($set = 0; $set < $grid->setCount; $set++)
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $row->color }}">
                                                    {{ $row->cells[$week][$set] ?? '-' }}
                                                </td>
                                            @endfor
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
