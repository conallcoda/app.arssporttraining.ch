@props([
    'grid',
    'name' => 'Untitled',
    'summary' => null,
    'badges' => [],
    'showMenu' => true,
    'showHeader' => true,
    'editable' => true,
    'weekLabels' => null,
    'weekSessions' => null,
    'collapseWeeks' => true,
    'settingClickable' => false,
    'sessionLabels' => false,
])

@if (count($grid->rows) === 0 && count($grid->weekColumns) === 0)
    <div class="text-center text-zinc-500 dark:text-zinc-400 py-8">
        {{ __('No settings configured for this exercise.') }}
    </div>
@else
    <div class="{{ $showHeader ? 'space-y-2 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4' : 'space-y-2' }}" x-data="{ expanded: false }">
        @if ($showHeader)
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <flux:heading size="lg">{{ $name }}</flux:heading>

                    @if ($summary)
                        @php
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

                    @if (! empty($badges))
                        <div class="flex flex-wrap gap-1">
                            @foreach ($badges as $badge)
                                @if (is_array($badge))
                                    <flux:badge size="sm" color="{{ $badge['color'] ?? '' }}">{{ $badge['label'] }}</flux:badge>
                                @else
                                    <flux:badge size="sm">{{ $badge }}</flux:badge>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($showMenu)
                    <flux:dropdown>
                        <flux:button variant="ghost" size="sm" icon="ellipsis" class="!p-1" />
                        <flux:menu>
                            <div x-show="!expanded">
                                <flux:menu.item icon="unfold-vertical" x-on:click="expanded = true">{{ __('Expand') }}</flux:menu.item>
                            </div>
                            <div x-show="expanded" x-cloak>
                                <flux:menu.item icon="fold-vertical" x-on:click="expanded = false">{{ __('Collapse') }}</flux:menu.item>
                            </div>
                            <flux:menu.item icon="pencil" wire:click="openSettingsForm">{{ __('Edit Settings') }}</flux:menu.item>
                            <flux:menu.item icon="rotate-ccw" wire:click="resetOverrides">{{ __('Reset Overrides') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                @endif
            </div>
        @endif

        @php
            $showWeekColumn = $collapseWeeks ? $grid->weekCount > 1 : true;
            $showCopyMenu = $editable && $grid->weekCount > 1;
        @endphp
        <div class="overflow-x-auto text-sm">
            <table class="border-collapse border border-zinc-300 dark:border-zinc-600 table-fixed">
                <thead>
                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                        @if ($showWeekColumn)
                            <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-20">{{ __('Week') }}</th>
                        @endif
                        <th class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 w-12" x-show="expanded" x-cloak>{{ __('Session') }}</th>
                        @if (count($grid->rows) > 0)
                            <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2"></th>
                        @endif
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
                        @if (count($grid->rows) > 0)
                            @for ($i = 0; $i < $grid->setCount; $i++)
                                @if ($settingClickable)
                                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 {{ $setColWidth }} whitespace-nowrap cursor-pointer hover:brightness-125"
                                        @click="$dispatch('grid-setting-click', { field: 'sets' })">
                                @else
                                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 {{ $setColWidth }} whitespace-nowrap">
                                @endif
                                    @if ($grid->setCount > 1)
                                        {{ $grid->setLabel }} {{ $i + 1 }}
                                    @endif
                                </th>
                            @endfor
                        @endif
                        @foreach ($grid->weekColumns as $weekCol)
                            @if ($settingClickable)
                                <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16 whitespace-nowrap cursor-pointer hover:brightness-125"
                                    @click="$dispatch('grid-setting-click', { field: '{{ Str::snake($weekCol->field) }}' })">
                            @else
                                <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16 whitespace-nowrap">
                            @endif
                                {{ $weekCol->label }}</th>
                        @endforeach
                        @if ($showCopyMenu)
                            <th class="border border-zinc-300 dark:border-zinc-600 px-1 py-2 w-8"></th>
                        @endif
                    </tr>
                </thead>
                <tbody x-show="!expanded">
                    @php $runningSessionCounter = 0; @endphp
                    @for ($week = 0; $week < $grid->weekCount; $week++)
                        @if (count($grid->rows) === 0)
                            <tr wire:key="collapsed-w{{ $week }}-weekonly">
                                @if ($showWeekColumn)
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center">
                                        <div class="whitespace-nowrap">{!! $weekLabels[$week] ?? 'TW' . ($week + 1) !!}</div>
                                        @php $weekSessionCount = $weekSessions[$week] ?? $grid->sessionsPerWeek; @endphp
                                        @if ($sessionLabels)
                                            @for ($s = 1; $s <= $weekSessionCount; $s++)
                                                <div class="text-[10px] font-normal text-zinc-400 dark:text-zinc-500 whitespace-nowrap">{{ __('Session') }} {{ $runningSessionCounter + $s }}</div>
                                            @endfor
                                            @php $runningSessionCounter += $weekSessionCount; @endphp
                                        @elseif ($weekSessionCount > 1)
                                            <div class="text-[10px] font-normal text-zinc-400 dark:text-zinc-500 whitespace-nowrap">({{ $weekSessionCount }} {{ __('sessions') }})</div>
                                        @elseif ($weekSessions !== null)
                                            <div class="text-[10px] font-normal text-zinc-400 dark:text-zinc-500 whitespace-nowrap">({{ $weekSessionCount }} {{ __('session') }})</div>
                                        @endif
                                    </td>
                                @endif
                                @foreach ($grid->weekColumns as $weekCol)
                                    @php
                                        $wcValue = $weekCol->cells[$week] ?? '-';
                                        $wcOverridden = $weekCol->overrides[$week] ?? false;
                                    @endphp
                                    @if ($editable && $weekCol->isCellEditable($week))
                                            <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCol->resolveCellColor($week, null, $wcOverridden) }}"
                                            x-data="editable_cell"
                                            data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                            data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                            data-edit-type="week"
                                            data-field="{{ $weekCol->field }}"
                                            data-week="{{ $week }}"
                                            @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                data-mask="{{ $weekCol->inputMeta->mask }}"
                                            @endif
                                            @click="startEditing()">
                                            <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $wcValue }}</span>
                                            <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$wcValue" size="xs" type="text" />
                                        </td>
                                    @else
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCol->resolveCellColor($week, null, false) }}">
                                            {{ $wcValue }}
                                        </td>
                                    @endif
                                @endforeach
                                @if ($showCopyMenu)
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-2 align-middle text-center bg-zinc-50 dark:bg-zinc-800/50">
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-0.5" />
                                            <flux:menu>
                                                <flux:menu.submenu heading="{{ __('Copy From') }}">
                                                    @for ($w = 0; $w < $grid->weekCount; $w++)
                                                        @if ($w !== $week)
                                                            <flux:menu.item wire:click="copyWeek({{ $w }}, {{ $week }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                        @endif
                                                    @endfor
                                                </flux:menu.submenu>
                                                <flux:menu.submenu heading="{{ __('Copy To') }}">
                                                    <flux:menu.item wire:click="copyWeekToAll({{ $week }})">{{ __('All') }}</flux:menu.item>
                                                    @for ($w = 0; $w < $grid->weekCount; $w++)
                                                        @if ($w !== $week)
                                                            <flux:menu.item wire:click="copyWeek({{ $week }}, {{ $w }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                        @endif
                                                    @endfor
                                                </flux:menu.submenu>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </td>
                                @endif
                            </tr>
                        @else
                            @foreach ($grid->rows as $rowIdx => $row)
                                <tr wire:key="collapsed-w{{ $week }}-r{{ $rowIdx }}">
                                    @if ($showWeekColumn && $rowIdx === 0)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                            rowspan="{{ count($grid->rows) }}">
                                            <div class="whitespace-nowrap">{!! $weekLabels[$week] ?? 'TW' . ($week + 1) !!}</div>
                                            @php $weekSessionCount = $weekSessions[$week] ?? $grid->sessionsPerWeek; @endphp
                                            @if ($sessionLabels)
                                                @for ($s = 1; $s <= $weekSessionCount; $s++)
                                                    <div class="text-[10px] font-normal text-zinc-400 dark:text-zinc-500 whitespace-nowrap">{{ __('Session') }} {{ $runningSessionCounter + $s }}</div>
                                                @endfor
                                                @php $runningSessionCounter += $weekSessionCount; @endphp
                                            @elseif ($weekSessionCount > 1)
                                                <div class="text-[10px] font-normal text-zinc-400 dark:text-zinc-500 whitespace-nowrap">({{ $weekSessionCount }} {{ __('sessions') }})</div>
                                            @elseif ($weekSessions !== null)
                                                <div class="text-[10px] font-normal text-zinc-400 dark:text-zinc-500 whitespace-nowrap">({{ $weekSessionCount }} {{ __('session') }})</div>
                                            @endif
                                        </td>
                                    @endif
                                    @php $settingKey = match($row->field) { 'oneRepMax' => 'weight', default => Str::snake($row->field) }; @endphp
                                    @if ($settingClickable)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap cursor-pointer hover:brightness-125 {{ $row->color }}"
                                            @click="$dispatch('grid-setting-click', { field: '{{ $settingKey }}' })">
                                    @else
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap {{ $row->color }}">
                                    @endif
                                        {{ $row->label }}
                                    </td>
                                    @for ($set = 0; $set < $grid->setCount; $set++)
                                        @php
                                            $cellValue = $row->cells[$week][$set] ?? '-';
                                            $cellOverridden = $row->overrides[$week][$set] ?? false;
                                        @endphp
                                        @if ($editable && $row->isCellEditable($week, $set) && $cellValue !== '-')
                                                <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $row->resolveCellColor($week, $set, $cellOverridden) }}"
                                                x-data="editable_cell"
                                            data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                            data-msg-invalid-value="{{ __('Please enter a valid value') }}"
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
                                                <x-training.exercise-grid-input :meta="$row->inputMeta" :value="$cellValue" size="sm" />
                                            </td>
                                        @else
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $row->resolveCellColor($week, $set, false) }}">
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
                                            @if ($editable && $weekCol->isCellEditable($week))
                                                @if ($wcOverridden)
                                                    <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCol->overrideColor }}"
                                                @else
                                                    <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCol->color }}"
                                                @endif
                                                    rowspan="{{ count($grid->rows) }}"
                                                    x-data="editable_cell"
                                            data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                            data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                    data-edit-type="week"
                                                    data-field="{{ $weekCol->field }}"
                                                    data-week="{{ $week }}"
                                                    @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                        data-mask="{{ $weekCol->inputMeta->mask }}"
                                                    @endif
                                                    @click="startEditing()">
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $wcValue }}</span>
                                                    <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$wcValue" size="xs" type="text" />
                                                </td>
                                            @else
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCol->color }}"
                                                    rowspan="{{ count($grid->rows) }}">
                                                    {{ $wcValue }}
                                                </td>
                                            @endif
                                        @endforeach
                                        @if ($showCopyMenu)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-2 align-middle text-center bg-zinc-50 dark:bg-zinc-800/50"
                                                rowspan="{{ count($grid->rows) }}">
                                                <flux:dropdown position="bottom" align="end">
                                                    <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-0.5" />
                                                    <flux:menu>
                                                        <flux:menu.submenu heading="{{ __('Copy From') }}">
                                                            @for ($w = 0; $w < $grid->weekCount; $w++)
                                                                @if ($w !== $week)
                                                                    <flux:menu.item wire:click="copyWeek({{ $w }}, {{ $week }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                                @endif
                                                            @endfor
                                                        </flux:menu.submenu>
                                                        <flux:menu.submenu heading="{{ __('Copy To') }}">
                                                            <flux:menu.item wire:click="copyWeekToAll({{ $week }})">{{ __('All') }}</flux:menu.item>
                                                            @for ($w = 0; $w < $grid->weekCount; $w++)
                                                                @if ($w !== $week)
                                                                    <flux:menu.item wire:click="copyWeek({{ $week }}, {{ $w }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                                @endif
                                                            @endfor
                                                        </flux:menu.submenu>
                                                    </flux:menu>
                                                </flux:dropdown>
                                            </td>
                                        @endif
                                    @endif
                                </tr>
                            @endforeach
                        @endif
                    @endfor
                </tbody>
                <tbody x-show="expanded" x-cloak>
                    @for ($week = 0; $week < $grid->weekCount; $week++)
                        @if (count($grid->rows) === 0)
                            <tr wire:key="expanded-w{{ $week }}-weekonly">
                                @if ($showWeekColumn)
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center">
                                        <div class="whitespace-nowrap">{!! $weekLabels[$week] ?? 'TW' . ($week + 1) !!}</div>
                                    </td>
                                @endif
                                @foreach ($grid->weekColumns as $weekCol)
                                    @php
                                        $wcValue = $weekCol->cells[$week] ?? '-';
                                        $wcOverridden = $weekCol->overrides[$week] ?? false;
                                    @endphp
                                    @if ($editable && $weekCol->isCellEditable($week))
                                            <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCol->resolveCellColor($week, null, $wcOverridden) }}"
                                            x-data="editable_cell"
                                            data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                            data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                            data-edit-type="week"
                                            data-field="{{ $weekCol->field }}"
                                            data-week="{{ $week }}"
                                            @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                data-mask="{{ $weekCol->inputMeta->mask }}"
                                            @endif
                                            @click="startEditing()">
                                            <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $wcValue }}</span>
                                            <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$wcValue" size="xs" type="text" />
                                        </td>
                                    @else
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCol->resolveCellColor($week, null, false) }}">
                                            {{ $wcValue }}
                                        </td>
                                    @endif
                                @endforeach
                                @if ($showCopyMenu)
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-2 align-middle text-center bg-zinc-50 dark:bg-zinc-800/50">
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-0.5" />
                                            <flux:menu>
                                                <flux:menu.submenu heading="{{ __('Copy From') }}">
                                                    @for ($w = 0; $w < $grid->weekCount; $w++)
                                                        @if ($w !== $week)
                                                            <flux:menu.item wire:click="copyWeek({{ $w }}, {{ $week }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                        @endif
                                                    @endfor
                                                </flux:menu.submenu>
                                                <flux:menu.submenu heading="{{ __('Copy To') }}">
                                                    <flux:menu.item wire:click="copyWeekToAll({{ $week }})">{{ __('All') }}</flux:menu.item>
                                                    @for ($w = 0; $w < $grid->weekCount; $w++)
                                                        @if ($w !== $week)
                                                            <flux:menu.item wire:click="copyWeek({{ $week }}, {{ $w }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                        @endif
                                                    @endfor
                                                </flux:menu.submenu>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </td>
                                @endif
                            </tr>
                        @else
                        @for ($session = 0; $session < $grid->sessionsPerWeek; $session++)
                            @foreach ($grid->rows as $rowIdx => $row)
                                @php $isFirstRow = $rowIdx === 0; @endphp
                                <tr wire:key="expanded-w{{ $week }}-s{{ $session }}-r{{ $rowIdx }}">
                                    @if ($showWeekColumn && $session === 0 && $isFirstRow)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                            rowspan="{{ $grid->sessionsPerWeek * count($grid->rows) }}">
                                            <div class="whitespace-nowrap">{!! $weekLabels[$week] ?? 'TW' . ($week + 1) !!}</div>
                                        </td>
                                    @endif
                                    @if ($isFirstRow)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center bg-zinc-100 dark:bg-zinc-700/50 text-xs font-medium"
                                            rowspan="{{ count($grid->rows) }}">
                                            {{ $session + 1 }}
                                        </td>
                                    @endif
                                    @php $settingKey = match($row->field) { 'oneRepMax' => 'weight', default => Str::snake($row->field) }; @endphp
                                    @if ($settingClickable)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap cursor-pointer hover:brightness-125 {{ $row->color }}"
                                            @click="$dispatch('grid-setting-click', { field: '{{ $settingKey }}' })">
                                    @else
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap {{ $row->color }}">
                                    @endif
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
                                        @if ($editable && $row->isCellEditable($week, $set) && $cellValue !== '-')
                                                <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $row->resolveCellColor($week, $set, $cellOverridden) }}"
                                                x-data="editable_cell"
                                            data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                            data-msg-invalid-value="{{ __('Please enter a valid value') }}"
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
                                                <x-training.exercise-grid-input :meta="$row->inputMeta" :value="$cellValue" size="sm" />
                                            </td>
                                        @else
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $row->resolveCellColor($week, $set, false) }}">
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
                                            @if ($editable && $weekCol->isCellEditable($week))
                                                @if ($wcOverridden)
                                                    <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCol->overrideColor }}"
                                                @else
                                                    <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCol->color }}"
                                                @endif
                                                    rowspan="{{ $grid->sessionsPerWeek * count($grid->rows) }}"
                                                    x-data="editable_cell"
                                            data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                            data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                    data-edit-type="week"
                                                    data-field="{{ $weekCol->field }}"
                                                    data-week="{{ $week }}"
                                                    @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                        data-mask="{{ $weekCol->inputMeta->mask }}"
                                                    @endif
                                                    @click="startEditing()">
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $wcValue }}</span>
                                                    <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$wcValue" size="xs" type="text" />
                                                </td>
                                            @else
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCol->color }}"
                                                    rowspan="{{ $grid->sessionsPerWeek * count($grid->rows) }}">
                                                    {{ $wcValue }}
                                                </td>
                                            @endif
                                        @endforeach
                                        @if ($showCopyMenu)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-2 align-middle text-center bg-zinc-50 dark:bg-zinc-800/50"
                                                rowspan="{{ $grid->sessionsPerWeek * count($grid->rows) }}">
                                                <flux:dropdown position="bottom" align="end">
                                                    <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-0.5" />
                                                    <flux:menu>
                                                        <flux:menu.submenu heading="{{ __('Copy From') }}">
                                                            @for ($w = 0; $w < $grid->weekCount; $w++)
                                                                @if ($w !== $week)
                                                                    <flux:menu.item wire:click="copyWeek({{ $w }}, {{ $week }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                                @endif
                                                            @endfor
                                                        </flux:menu.submenu>
                                                        <flux:menu.submenu heading="{{ __('Copy To') }}">
                                                            <flux:menu.item wire:click="copyWeekToAll({{ $week }})">{{ __('All') }}</flux:menu.item>
                                                            @for ($w = 0; $w < $grid->weekCount; $w++)
                                                                @if ($w !== $week)
                                                                    <flux:menu.item wire:click="copyWeek({{ $week }}, {{ $w }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                                @endif
                                                            @endfor
                                                        </flux:menu.submenu>
                                                    </flux:menu>
                                                </flux:dropdown>
                                            </td>
                                        @endif
                                    @endif
                                </tr>
                            @endforeach
                        @endfor
                        @endif
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
@endif
