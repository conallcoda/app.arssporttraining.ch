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
                    $hasOverrides = count($data['overrides']['cells'] ?? []) > 0 || count($data['overrides']['weeks'] ?? []) > 0;
                @endphp

                @if (count($grid->rows) === 0)
                    <div class="text-center text-zinc-500 dark:text-zinc-400 py-8">
                        Select settings to see preview.
                    </div>
                @else
                    <div class="w-fit space-y-2" x-data="{ expanded: false }">
                        <div class="flex items-center justify-between">
                            <flux:heading size="lg">{{ $data['name'] ?? 'Untitled' }}</flux:heading>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis" class="!p-1" />
                                <flux:menu>
                                    <div x-show="!expanded">
                                        <flux:menu.item icon="unfold-vertical" x-on:click="expanded = true">Expand</flux:menu.item>
                                    </div>
                                    <div x-show="expanded" x-cloak>
                                        <flux:menu.item icon="fold-vertical" x-on:click="expanded = false">Collapse</flux:menu.item>
                                    </div>
                                    <flux:menu.item icon="rotate-ccw" wire:click="resetOverrides">Reset</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>

                    <div class="overflow-x-auto text-sm" wire:key="preview-grid-{{ $grid->setCount }}-{{ $grid->weekCount }}-{{ count($grid->rows) }}-{{ $grid->sessionsPerWeek }}-{{ count($grid->weekColumns) }}">
                        <table class="border-collapse border border-zinc-300 dark:border-zinc-600 table-fixed">
                            <thead>
                                <tr class="bg-zinc-100 dark:bg-zinc-800">
                                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-20">Week</th>
                                    <th class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 w-12" x-show="expanded" x-cloak>Session</th>
                                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2"></th>
                                    @php
                                        $labelLen = mb_strlen($grid->setLabel);
                                        $setColWidth = match(true) {
                                            $labelLen <= 5 => 'w-20',
                                            $labelLen <= 10 => 'w-28',
                                            $labelLen <= 15 => 'w-36',
                                            $labelLen <= 20 => 'w-44',
                                            default => 'w-44',
                                        };
                                    @endphp
                                    @for ($i = 0; $i < $grid->setCount; $i++)
                                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 {{ $setColWidth }} whitespace-nowrap">
                                            {{ $grid->setLabel }} {{ $i + 1 }}</th>
                                    @endfor
                                    @foreach ($grid->weekColumns as $weekCol)
                                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16 whitespace-nowrap">
                                            {{ $weekCol->label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody x-show="!expanded">
                                @for ($week = 0; $week < $grid->weekCount; $week++)
                                    @foreach ($grid->rows as $rowIdx => $row)
                                        <tr wire:key="collapsed-w{{ $week }}-r{{ $rowIdx }}">
                                            @if ($rowIdx === 0)
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                                    rowspan="{{ count($grid->rows) }}">
                                                    <div>TW{{ $week + 1 }}</div>
                                                    @if ($grid->sessionsPerWeek > 1)
                                                        <div class="text-[10px] font-normal text-zinc-400 dark:text-zinc-500 whitespace-nowrap">({{ $grid->sessionsPerWeek }} sessions)</div>
                                                    @endif
                                                </td>
                                            @endif
                                            <td
                                                class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap {{ $row->color }}">
                                                {{ $row->label }}
                                            </td>
                                            @for ($set = 0; $set < $grid->setCount; $set++)
                                                @php
                                                    $cellValue = $row->cells[$week][$set] ?? '-';
                                                    $cellOverridden = $row->overrides[$week][$set] ?? false;
                                                @endphp
                                                @if ($row->isCellEditable($week, $set) && $cellValue !== '-')
                                                    @if ($cellOverridden)
                                                        <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $row->overrideColor }}"
                                                    @else
                                                        <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $row->color }}"
                                                    @endif
                                                        x-data="editable_cell"
                                                        data-edit-type="cell"
                                                        data-field="{{ $row->field }}"
                                                        data-week="{{ $week }}"
                                                        data-set="{{ $set }}"
                                                        data-session="{{ $grid->sessionsPerWeek - 1 }}"
                                                        data-apply-to-all="true"
                                                        @if ($row->inputMeta && $row->inputMeta->mask)
                                                            data-mask="{{ $row->inputMeta->mask }}"
                                                        @endif
                                                        @click="startEditing()">
                                                        <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $cellValue }}</span>
                                                        <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                                            @if ($row->inputMeta)
                                                                type="{{ $row->inputMeta->inputType }}"
                                                                @if ($row->inputMeta->inputType === 'number')
                                                                    step="{{ $row->inputMeta->inputStep }}"
                                                                @endif
                                                                @if ($row->inputMeta->min !== null)
                                                                    min="{{ $row->inputMeta->min }}"
                                                                @endif
                                                                @if ($row->inputMeta->max !== null)
                                                                    max="{{ $row->inputMeta->max }}"
                                                                @endif
                                                                @if ($row->inputMeta->maxlength !== null)
                                                                    maxlength="{{ $row->inputMeta->maxlength }}"
                                                                @endif
                                                            @else
                                                                type="number"
                                                            @endif
                                                            class="w-full h-full px-2 py-1.5 text-center text-sm bg-transparent border-0 focus:ring-0"
                                                            placeholder="{{ $cellValue }}"
                                                            @blur="commitEdit()"
                                                            @keydown="handleKeydown($event)"
                                                            @input="applyMask($event)" />
                                                    </td>
                                                @else
                                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $row->color }}">
                                                        {{ $cellValue }}
                                                    </td>
                                                @endif
                                            @endfor
                                            @if ($rowIdx === 0)
                                                @foreach ($grid->weekColumns as $weekCol)
                                                    @php
                                                        $wcValue = $weekCol->cells[$week] ?? '-';
                                                        $wcOverridden = $weekCol->overrides[$week] ?? false;
                                                    @endphp
                                                    @if ($weekCol->isCellEditable($week))
                                                        @if ($wcOverridden)
                                                            <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCol->overrideColor }}"
                                                        @else
                                                            <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCol->color }}"
                                                        @endif
                                                            rowspan="{{ count($grid->rows) }}"
                                                            x-data="editable_cell"
                                                            data-edit-type="week"
                                                            data-field="{{ $weekCol->field }}"
                                                            data-week="{{ $week }}"
                                                            @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                                data-mask="{{ $weekCol->inputMeta->mask }}"
                                                            @endif
                                                            @click="startEditing()">
                                                            <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $wcValue }}</span>
                                                            <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                                                @if ($weekCol->inputMeta)
                                                                    type="{{ $weekCol->inputMeta->inputType }}"
                                                                    @if ($weekCol->inputMeta->inputType === 'number')
                                                                        step="{{ $weekCol->inputMeta->inputStep }}"
                                                                    @endif
                                                                    @if ($weekCol->inputMeta->min !== null)
                                                                        min="{{ $weekCol->inputMeta->min }}"
                                                                    @endif
                                                                    @if ($weekCol->inputMeta->max !== null)
                                                                        max="{{ $weekCol->inputMeta->max }}"
                                                                    @endif
                                                                    @if ($weekCol->inputMeta->maxlength !== null)
                                                                        maxlength="{{ $weekCol->inputMeta->maxlength }}"
                                                                    @endif
                                                                @else
                                                                    type="text"
                                                                @endif
                                                                class="w-full h-full px-2 py-1.5 text-center text-xs bg-transparent border-0 focus:ring-0"
                                                                placeholder="{{ $wcValue }}"
                                                                @blur="commitEdit()"
                                                                @keydown="handleKeydown($event)"
                                                                @input="applyMask($event)" />
                                                        </td>
                                                    @else
                                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCol->color }}"
                                                            rowspan="{{ count($grid->rows) }}">
                                                            {{ $wcValue }}
                                                        </td>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </tr>
                                    @endforeach
                                @endfor
                            </tbody>
                            <tbody x-show="expanded" x-cloak>
                                @for ($week = 0; $week < $grid->weekCount; $week++)
                                    @for ($session = 0; $session < $grid->sessionsPerWeek; $session++)
                                        @foreach ($grid->rows as $rowIdx => $row)
                                            @php $isFirstRow = $rowIdx === 0; @endphp
                                            <tr wire:key="expanded-w{{ $week }}-s{{ $session }}-r{{ $rowIdx }}">
                                                @if ($session === 0 && $isFirstRow)
                                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                                        rowspan="{{ $grid->sessionsPerWeek * count($grid->rows) }}">
                                                        <div>TW{{ $week + 1 }}</div>
                                                    </td>
                                                @endif
                                                @if ($isFirstRow)
                                                    <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center bg-zinc-100 dark:bg-zinc-700/50 text-xs font-medium"
                                                        rowspan="{{ count($grid->rows) }}">
                                                        {{ $session + 1 }}
                                                    </td>
                                                @endif
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap {{ $row->color }}">
                                                    {{ $row->label }}
                                                </td>
                                                @php
                                                    $isLastSession = $session === $grid->sessionsPerWeek - 1;
                                                @endphp
                                                @for ($set = 0; $set < $grid->setCount; $set++)
                                                    @php
                                                        $cellValue = ($row->lastSessionOnly && !$isLastSession)
                                                            ? '-'
                                                            : ($row->cells[$week][$set] ?? '-');
                                                        $cellOverridden = $row->overrides[$week][$set] ?? false;
                                                    @endphp
                                                    @if ($row->isCellEditable($week, $set) && $cellValue !== '-')
                                                        @if ($cellOverridden)
                                                            <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $row->overrideColor }}"
                                                        @else
                                                            <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $row->color }}"
                                                        @endif
                                                            x-data="editable_cell"
                                                            data-edit-type="cell"
                                                            data-field="{{ $row->field }}"
                                                            data-week="{{ $week }}"
                                                            data-set="{{ $set }}"
                                                            data-session="{{ $session }}"
                                                            @if ($row->inputMeta && $row->inputMeta->mask)
                                                                data-mask="{{ $row->inputMeta->mask }}"
                                                            @endif
                                                            @click="startEditing()">
                                                            <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $cellValue }}</span>
                                                            <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                                                @if ($row->inputMeta)
                                                                    type="{{ $row->inputMeta->inputType }}"
                                                                    @if ($row->inputMeta->inputType === 'number')
                                                                        step="{{ $row->inputMeta->inputStep }}"
                                                                    @endif
                                                                    @if ($row->inputMeta->min !== null)
                                                                        min="{{ $row->inputMeta->min }}"
                                                                    @endif
                                                                    @if ($row->inputMeta->max !== null)
                                                                        max="{{ $row->inputMeta->max }}"
                                                                    @endif
                                                                    @if ($row->inputMeta->maxlength !== null)
                                                                        maxlength="{{ $row->inputMeta->maxlength }}"
                                                                    @endif
                                                                @else
                                                                    type="number"
                                                                @endif
                                                                class="w-full h-full px-2 py-1.5 text-center text-sm bg-transparent border-0 focus:ring-0"
                                                                placeholder="{{ $cellValue }}"
                                                                @blur="commitEdit()"
                                                                @keydown="handleKeydown($event)"
                                                                @input="applyMask($event)" />
                                                        </td>
                                                    @else
                                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $row->color }}">
                                                            {{ $cellValue }}
                                                        </td>
                                                    @endif
                                                @endfor
                                                @if ($session === 0 && $isFirstRow)
                                                    @foreach ($grid->weekColumns as $weekCol)
                                                        @php
                                                            $wcValue = $weekCol->cells[$week] ?? '-';
                                                            $wcOverridden = $weekCol->overrides[$week] ?? false;
                                                        @endphp
                                                        @if ($weekCol->isCellEditable($week))
                                                            @if ($wcOverridden)
                                                                <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCol->overrideColor }}"
                                                            @else
                                                                <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCol->color }}"
                                                            @endif
                                                                rowspan="{{ $grid->sessionsPerWeek * count($grid->rows) }}"
                                                                x-data="editable_cell"
                                                                data-edit-type="week"
                                                                data-field="{{ $weekCol->field }}"
                                                                data-week="{{ $week }}"
                                                                @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                                    data-mask="{{ $weekCol->inputMeta->mask }}"
                                                                @endif
                                                                @click="startEditing()">
                                                                <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $wcValue }}</span>
                                                                <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                                                    @if ($weekCol->inputMeta)
                                                                        type="{{ $weekCol->inputMeta->inputType }}"
                                                                        @if ($weekCol->inputMeta->inputType === 'number')
                                                                            step="{{ $weekCol->inputMeta->inputStep }}"
                                                                        @endif
                                                                        @if ($weekCol->inputMeta->min !== null)
                                                                            min="{{ $weekCol->inputMeta->min }}"
                                                                        @endif
                                                                        @if ($weekCol->inputMeta->max !== null)
                                                                            max="{{ $weekCol->inputMeta->max }}"
                                                                        @endif
                                                                        @if ($weekCol->inputMeta->maxlength !== null)
                                                                            maxlength="{{ $weekCol->inputMeta->maxlength }}"
                                                                        @endif
                                                                    @else
                                                                        type="text"
                                                                    @endif
                                                                    class="w-full h-full px-2 py-1.5 text-center text-xs bg-transparent border-0 focus:ring-0"
                                                                    placeholder="{{ $wcValue }}"
                                                                    @blur="commitEdit()"
                                                                    @keydown="handleKeydown($event)"
                                                                    @input="applyMask($event)" />
                                                            </td>
                                                        @else
                                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCol->color }}"
                                                                rowspan="{{ $grid->sessionsPerWeek * count($grid->rows) }}">
                                                                {{ $wcValue }}
                                                            </td>
                                                        @endif
                                                    @endforeach
                                                @endif
                                            </tr>
                                        @endforeach
                                    @endfor
                                @endfor
                            </tbody>
                        </table>
                    </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</flux:main>
