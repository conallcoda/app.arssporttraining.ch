@props([
    'block',
    'exercise',
    'exerciseId',
    'config',
    'gridDefinition',
    'headerInfo' => null,
    'badges' => [],
    'settingsFields' => [],
    'cellOverrides' => [],
    'userSpecificCellOverrides' => [],
    'weekOverrides' => [],
    'userSpecificWeekOverrides' => [],
    'mergeIdenticalSessions' => true,
    'isDefaultUser' => false,
    'startDate' => null,
])

@php
    $weeks = $block?->weeks ?? [];
    $setRows = $gridDefinition->setRows;
    $weekColumns = $gridDefinition->weekColumns;
    $rowCount = count($setRows);

    $setCount = 0;
    foreach ($weeks as $week) {
        foreach ($week->sessions as $session) {
            $setCount = max($setCount, count($session->sets));
        }
    }
    $setCount = $setCount ?: 1;

    $sessionsAreIdentical = function ($sessions) {
        if (count($sessions) < 2) {
            return true;
        }
        $firstSessionSets = array_map(fn($set) => $set->toArray(), $sessions[0]->sets);
        foreach (array_slice($sessions, 1) as $session) {
            $sessionSets = array_map(fn($set) => $set->toArray(), $session->sets);
            if ($firstSessionSets !== $sessionSets) {
                return false;
            }
        }
        return true;
    };

    $hasOverride = function ($weekIndex, $sessionIndex, $setIndex, $field) use ($userSpecificCellOverrides) {
        foreach ($userSpecificCellOverrides as $override) {
            if ($override['week'] === $weekIndex && $override['session'] === $sessionIndex && $override['set'] === $setIndex && isset($override['data'][$field])) {
                return true;
            }
        }
        return false;
    };

    $findWeekData = function ($overrides, $weekIndex) {
        foreach ($overrides as $override) {
            if ($override['week'] === $weekIndex) {
                return $override['data'] ?? [];
            }
        }
        return [];
    };

    $hasWeekOverride = function ($weekIndex, $field) use ($userSpecificWeekOverrides, $findWeekData) {
        $data = $findWeekData($userSpecificWeekOverrides, $weekIndex);
        return isset($data[$field]);
    };

    $getWeekColumnValue = function ($weekIndex, $field) use ($weekOverrides, $config, $findWeekData) {
        $data = $findWeekData($weekOverrides, $weekIndex);
        return $data[$field] ?? $config[$field] ?? null;
    };

    $getCalendarWeek = function ($weekIndex) use ($startDate) {
        if (!$startDate) {
            return null;
        }
        $date = \Carbon\Carbon::parse($startDate)->addWeeks($weekIndex);
        return $date->year . ' - W' . $date->isoWeek();
    };

    $isEditable = function ($row, $isDefaultUser) {
        return match ($row->editableMode) {
            'always' => true,
            'user-only' => !$isDefaultUser,
            'never' => false,
            default => true,
        };
    };
@endphp

<div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
    <div class="mb-4">
        <div class="flex items-start justify-between">
            <flux:heading size="lg">{{ $exercise->name }}</flux:heading>
            <flux:dropdown>
                <flux:button variant="ghost" size="sm" icon="ellipsis" class="!p-1" />
                <flux:menu>
                    <flux:menu.item icon="rotate-ccw" wire:click="resetExerciseOverrides({{ $exerciseId }})">Reset</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
        @if ($headerInfo)
            @if (($headerInfo['type'] ?? '') === 'strength_automatic')
                @php
                    $modifierDiff = $headerInfo['modifierDiff'] ?? 0;
                    $targetPercent = $headerInfo['targetPercent'] ?? 0;
                @endphp
                <div class="flex items-center gap-2 text-sm mt-1">
                    <span class="font-medium text-amber-600 dark:text-amber-400">{{ $headerInfo['starting1RM'] }}kg</span>
                    @if ($modifierDiff !== 0)
                        <span class="text-zinc-500 dark:text-zinc-400">({{ $modifierDiff > 0 ? '+' : '' }}{{ $modifierDiff }}%)</span>
                    @endif
                    <flux:icon.arrow-right class="size-4 text-zinc-400" />
                    <span class="font-medium text-green-600 dark:text-green-400">{{ $headerInfo['target1RM'] }}kg</span>
                    <span class="text-zinc-500 dark:text-zinc-400">(+{{ $targetPercent }}%)</span>
                </div>
            @elseif (($headerInfo['type'] ?? '') === 'strength_manual')
                <div class="flex items-center gap-2 text-sm mt-1">
                    <span class="font-medium text-amber-600 dark:text-amber-400">{{ $headerInfo['startingWeight'] }}kg</span>
                </div>
            @endif
        @endif
    </div>

    @php
        $modalName = "exercise-settings-{$exerciseId}";
    @endphp

    <div class="flex flex-wrap gap-1 mb-4">
        @foreach ($badges as $badge)
            <flux:badge size="sm"
                class="cursor-pointer {{ $badge['hasOverride'] ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300' : '' }}"
                x-on:click="$flux.modal('{{ $modalName }}').show(); $nextTick(() => $dispatch('focus-field', { field: '{{ $badge['field'] }}' }))">
                {{ $badge['label'] }}
            </flux:badge>
        @endforeach
    </div>

    <flux:modal :name="$modalName" flyout class="w-96"
        x-on:focus-field.window="$nextTick(() => {
        const input = $el.querySelector(`[data-field='${$event.detail.field}']`);
        if (input) input.focus();
    })">
        <div class="space-y-6" x-data="{
            @foreach ($settingsFields as $sf)
                @if ($sf['type'] === 'text')
                    {{ $sf['field'] }}: '{{ $config[$sf['field']] ?? $sf['placeholder'] ?? '' }}',
                @else
                    {{ $sf['field'] }}: {{ $config[$sf['field']] ?? 0 }},
                @endif
            @endforeach
            save() {
                @foreach ($settingsFields as $sf)
                    @if ($sf['type'] === 'text')
                        $wire.updateExerciseOverride({{ $exerciseId }}, '{{ $sf['field'] }}', this.{{ $sf['field'] }});
                    @elseif (str_contains(($sf['step'] ?? '1') . '', '.'))
                        $wire.updateExerciseOverride({{ $exerciseId }}, '{{ $sf['field'] }}', parseFloat(this.{{ $sf['field'] }}));
                    @else
                        $wire.updateExerciseOverride({{ $exerciseId }}, '{{ $sf['field'] }}', parseInt(this.{{ $sf['field'] }}));
                    @endif
                @endforeach
                $flux.modal('{{ $modalName }}').close();
            }
        }">
            <flux:heading size="lg">{{ $exercise->name }}</flux:heading>

            <div class="space-y-4">
                @foreach ($settingsFields as $sf)
                    @php
                        $sfMin = $sf['min'] ?? null;
                        $sfMax = $sf['max'] ?? null;
                        $sfStep = $sf['step'] ?? null;
                        $sfMaxlength = $sf['maxlength'] ?? null;
                        $sfPlaceholder = $sf['placeholder'] ?? null;
                    @endphp
                    <flux:field>
                        <flux:label>{{ $sf['label'] }}</flux:label>
                        @if (!empty($sf['suffix']))
                            <flux:input.group>
                                <flux:input x-model="{{ $sf['field'] }}" :type="$sf['type']" :min="$sfMin" :max="$sfMax" :step="$sfStep" :maxlength="$sfMaxlength" :placeholder="$sfPlaceholder" :data-field="$sf['field']" />
                                <flux:input.group.suffix>{{ $sf['suffix'] }}</flux:input.group.suffix>
                            </flux:input.group>
                        @else
                            <flux:input x-model="{{ $sf['field'] }}" :type="$sf['type']" :maxlength="$sfMaxlength" :placeholder="$sfPlaceholder" :data-field="$sf['field']" />
                        @endif
                        @if (!empty($sf['description']))
                            <flux:description>{{ $sf['description'] }}</flux:description>
                        @endif
                    </flux:field>
                @endforeach
            </div>

            <div class="flex gap-2 pt-4">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" x-on:click="save()">Save</flux:button>
            </div>
        </div>
    </flux:modal>

    @if ($block && count($weeks) > 0)
        <div class="overflow-x-auto text-sm">
            <table class="border-collapse border border-zinc-300 dark:border-zinc-600 table-fixed">
                @php
                    $anyWeekNotMerged = false;
                    foreach ($weeks as $week) {
                        if (!($mergeIdenticalSessions && $sessionsAreIdentical($week->sessions))) {
                            $anyWeekNotMerged = true;
                            break;
                        }
                    }
                @endphp
                <thead>
                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-14">Week</th>
                        @if ($anyWeekNotMerged)
                            <th class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 w-12">#</th>
                        @endif
                        @for ($i = 0; $i < $setCount; $i++)
                            <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16">Set {{ $i + 1 }}</th>
                        @endfor
                        @foreach ($weekColumns as $wc)
                            <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-24">{{ $wc->label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weeks as $weekIndex => $week)
                        @php
                            $sessionCount = count($week->sessions);
                            $merged = $mergeIdenticalSessions && $sessionsAreIdentical($week->sessions);
                        @endphp
                        @if ($merged)
                            @php
                                $session = $week->sessions[0];
                                $sessionIndex = 0;
                            @endphp
                            @foreach ($setRows as $rowIdx => $row)
                                @php $isFirstRow = $rowIdx === 0; @endphp
                                <tr>
                                    @if ($isFirstRow)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                            rowspan="{{ $rowCount }}">
                                            <div>TW{{ $weekIndex + 1 }}</div>
                                            @if ($getCalendarWeek($weekIndex))
                                                <div class="text-[10px] font-normal text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                                                    {{ $getCalendarWeek($weekIndex) }}</div>
                                            @endif
                                            @if (!$anyWeekNotMerged)
                                                <div class="text-[10px] font-normal text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                                                    ({{ $sessionCount }} {{ Str::plural('Session', $sessionCount) }})</div>
                                            @endif
                                        </td>
                                        @if ($anyWeekNotMerged)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center bg-zinc-100 dark:bg-zinc-700/50 text-xs font-medium"
                                                rowspan="{{ $rowCount }}">
                                                {{ $sessionCount }}
                                            </td>
                                        @endif
                                    @endif
                                    @for ($i = 0; $i < $setCount; $i++)
                                        @if (isset($session->sets[$i]))
                                            @php
                                                $cellValue = $session->sets[$i]->{$row->field} ?? '-';
                                                $cellOverridden = $hasOverride($weekIndex, $sessionIndex, $i, $row->field);
                                                $cellColor = $cellOverridden ? $row->overrideColor : $row->color;
                                                $canEdit = $isEditable($row, $isDefaultUser);
                                            @endphp
                                            @if ($canEdit)
                                                <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $cellColor }}"
                                                    x-data="{ editing: false, editValue: '' }"
                                                    @click="editing = true; editValue = ''; $nextTick(() => $refs.input.focus())">
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $cellValue }}</span>
                                                    <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                                        type="{{ $row->inputType }}"
                                                        @if ($row->inputType === 'number') min="0" step="{{ $row->inputStep }}" @endif
                                                        class="w-full h-full px-2 py-1.5 text-center text-sm bg-transparent border-0 focus:ring-0"
                                                        placeholder="{{ $cellValue !== '-' ? $cellValue : '' }}"
                                                        @blur="editing = false; if (editValue !== '') $wire.updateCellOverride({{ $exerciseId }}, {{ $weekIndex }}, {{ $i }}, '{{ $row->field }}', {{ $row->inputType === 'number' ? ($row->inputStep === '1' ? 'parseInt(editValue)' : 'parseFloat(editValue)') : 'editValue' }})"
                                                        @keydown.enter="$event.target.blur()"
                                                        @keydown.escape="editing = false" />
                                                </td>
                                            @else
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-{{ $row->editableMode === 'never' ? '1' : '2' }} text-center {{ $row->editableMode === 'never' ? 'text-xs text-zinc-500 dark:text-zinc-400' : '' }} {{ $cellColor }}">
                                                    {{ $cellValue }}
                                                </td>
                                            @endif
                                        @else
                                            @if ($isFirstRow)
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center"
                                                    rowspan="{{ $rowCount }}"></td>
                                            @endif
                                        @endif
                                    @endfor
                                    @if ($isFirstRow)
                                        @foreach ($weekColumns as $wc)
                                            @php
                                                $wcValue = $getWeekColumnValue($weekIndex, $wc->field);
                                                $wcOverridden = $hasWeekOverride($weekIndex, $wc->field);
                                                $wcColor = $wcOverridden ? $wc->overrideColor : $wc->color;
                                                $wcDisplay = $wcValue . ($wc->suffix ?? '');
                                            @endphp
                                            <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $wcColor }}"
                                                rowspan="{{ $rowCount }}"
                                                x-data="{ editing: false, editValue: '' }"
                                                @click="editing = true; editValue = ''; $nextTick(() => $refs.input.focus())">
                                                <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $wcDisplay }}</span>
                                                <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                                    type="{{ $wc->inputType }}"
                                                    @if ($wc->inputType === 'number') min="0" @if ($wc->inputStep) step="{{ $wc->inputStep }}" @endif @endif
                                                    @if ($wc->inputType === 'text') maxlength="4" @endif
                                                    class="w-full h-full px-2 py-1.5 text-center text-xs bg-transparent border-0 focus:ring-0"
                                                    placeholder="{{ $wcValue }}"
                                                    @blur="editing = false; if (editValue !== '') $wire.updateWeekOverride({{ $exerciseId }}, {{ $weekIndex }}, '{{ $wc->field }}', {{ $wc->inputType === 'number' ? 'parseInt(editValue)' : 'editValue' }})"
                                                    @keydown.enter="$event.target.blur()"
                                                    @keydown.escape="editing = false" />
                                            </td>
                                        @endforeach
                                    @endif
                                </tr>
                            @endforeach
                        @else
                            @foreach ($week->sessions as $sessionIndex => $session)
                                @php $isFirstSession = $sessionIndex === 0; @endphp
                                @foreach ($setRows as $rowIdx => $row)
                                    @php $isFirstRow = $rowIdx === 0; @endphp
                                    <tr>
                                        @if ($isFirstSession && $isFirstRow)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                                rowspan="{{ $sessionCount * $rowCount }}">
                                                <div>TW{{ $weekIndex + 1 }}</div>
                                                @if ($getCalendarWeek($weekIndex))
                                                    <div class="text-[10px] font-normal text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                                                        {{ $getCalendarWeek($weekIndex) }}</div>
                                                @endif
                                            </td>
                                        @endif
                                        @if ($isFirstRow && $anyWeekNotMerged)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center bg-zinc-100 dark:bg-zinc-700/50 text-xs font-medium"
                                                rowspan="{{ $rowCount }}">
                                                S{{ $sessionIndex + 1 }}
                                            </td>
                                        @endif
                                        @for ($i = 0; $i < $setCount; $i++)
                                            @if (isset($session->sets[$i]))
                                                @php
                                                    $cellValue = $session->sets[$i]->{$row->field} ?? '-';
                                                    $cellOverridden = $hasOverride($weekIndex, $sessionIndex, $i, $row->field);
                                                    $cellColor = $cellOverridden ? $row->overrideColor : $row->color;
                                                    $canEdit = $isEditable($row, $isDefaultUser);
                                                @endphp
                                                @if ($canEdit)
                                                    <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $cellColor }}"
                                                        x-data="{ editing: false, editValue: '' }"
                                                        @click="editing = true; editValue = ''; $nextTick(() => $refs.input.focus())">
                                                        <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $cellValue }}</span>
                                                        <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                                            type="{{ $row->inputType }}"
                                                            @if ($row->inputType === 'number') min="0" step="{{ $row->inputStep }}" @endif
                                                            class="w-full h-full px-2 py-1.5 text-center text-sm bg-transparent border-0 focus:ring-0"
                                                            placeholder="{{ $cellValue !== '-' ? $cellValue : '' }}"
                                                            @blur="editing = false; if (editValue !== '') $wire.updateCellOverride({{ $exerciseId }}, {{ $weekIndex }}, {{ $i }}, '{{ $row->field }}', {{ $row->inputType === 'number' ? ($row->inputStep === '1' ? 'parseInt(editValue)' : 'parseFloat(editValue)') : 'editValue' }})"
                                                            @keydown.enter="$event.target.blur()"
                                                            @keydown.escape="editing = false" />
                                                    </td>
                                                @else
                                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-{{ $row->editableMode === 'never' ? '1' : '2' }} text-center {{ $row->editableMode === 'never' ? 'text-xs text-zinc-500 dark:text-zinc-400' : '' }} {{ $cellColor }}">
                                                        {{ $cellValue }}
                                                    </td>
                                                @endif
                                            @else
                                                @if ($isFirstRow)
                                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center"
                                                        rowspan="{{ $rowCount }}"></td>
                                                @endif
                                            @endif
                                        @endfor
                                        @if ($isFirstSession && $isFirstRow)
                                            @foreach ($weekColumns as $wc)
                                                @php
                                                    $wcValue = $getWeekColumnValue($weekIndex, $wc->field);
                                                    $wcOverridden = $hasWeekOverride($weekIndex, $wc->field);
                                                    $wcColor = $wcOverridden ? $wc->overrideColor : $wc->color;
                                                    $wcDisplay = $wcValue . ($wc->suffix ?? '');
                                                @endphp
                                                <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $wcColor }}"
                                                    rowspan="{{ $sessionCount * $rowCount }}"
                                                    x-data="{ editing: false, editValue: '' }"
                                                    @click="editing = true; editValue = ''; $nextTick(() => $refs.input.focus())">
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $wcDisplay }}</span>
                                                    <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                                        type="{{ $wc->inputType }}"
                                                        @if ($wc->inputType === 'number') min="0" @if ($wc->inputStep) step="{{ $wc->inputStep }}" @endif @endif
                                                        @if ($wc->inputType === 'text') maxlength="4" @endif
                                                        class="w-full h-full px-2 py-1.5 text-center text-xs bg-transparent border-0 focus:ring-0"
                                                        placeholder="{{ $wcValue }}"
                                                        @blur="editing = false; if (editValue !== '') $wire.updateWeekOverride({{ $exerciseId }}, {{ $weekIndex }}, '{{ $wc->field }}', {{ $wc->inputType === 'number' ? 'parseInt(editValue)' : 'editValue' }})"
                                                        @keydown.enter="$event.target.blur()"
                                                        @keydown.escape="editing = false" />
                                                </td>
                                            @endforeach
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="text-center text-zinc-500 dark:text-zinc-400 py-4">
            No data available for this exercise.
        </div>
    @endif
</div>
