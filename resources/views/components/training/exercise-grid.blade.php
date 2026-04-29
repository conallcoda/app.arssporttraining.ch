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
    'expandedWeeks' => [],
    'lockedSessionsByWeek' => [],
    'collapseWeeks' => true,
    'settingClickable' => false,
    'sessionLabels' => false,
])

@if (count($grid->rows) === 0 && count($grid->weekColumns) === 0)
    <div class="text-center text-zinc-500 dark:text-zinc-400 py-8">
        {{ __('No settings configured for this exercise.') }}
    </div>
@else
    @php
        $preparedWeeks = $grid->weeks ?? [];
        $hasPreparedWeeks = count($preparedWeeks) === $grid->weekCount;
        $expandedWeekLookup = $hasPreparedWeeks
            ? collect($preparedWeeks)->mapWithKeys(fn ($week) => [$week->index => $week->expanded])->all()
            : collect($expandedWeeks ?? [])->mapWithKeys(fn ($week) => [(int) $week => true])->all();
        $showSessionColumn = $hasPreparedWeeks ? $grid->showSessionColumn : ! empty($expandedWeekLookup);
    @endphp
    <div class="{{ $showHeader ? 'space-y-2 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4' : 'space-y-2' }}" x-data="{ expandedAll: false }">
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
                            <div x-show="!expandedAll">
                                <flux:menu.item icon="unfold-vertical" x-on:click="expandedAll = true">{{ __('Expand') }}</flux:menu.item>
                            </div>
                            <div x-show="expandedAll" x-cloak>
                                <flux:menu.item icon="fold-vertical" x-on:click="expandedAll = false">{{ __('Collapse') }}</flux:menu.item>
                            </div>
                            <flux:menu.item icon="pencil" wire:click="openSettingsForm">{{ __('Edit Settings') }}</flux:menu.item>
                            <flux:menu.item icon="rotate-ccw" wire:click="resetOverrides">{{ __('Reset Overrides') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                @endif
            </div>
        @endif

        @php
            $showWeekColumn = $hasPreparedWeeks ? $grid->showWeekColumn : ($collapseWeeks ? $grid->weekCount > 1 : true);
            $showCopyMenu = $hasPreparedWeeks ? ($editable && $grid->showCopyMenu) : ($editable && $grid->weekCount > 1);
        @endphp
        <div class="overflow-x-auto text-sm">
            <table class="border-collapse border border-zinc-300 dark:border-zinc-600 table-fixed">
                <thead>
                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                        @if ($showWeekColumn)
                            <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-20">{{ __('Week') }}</th>
                        @endif
                        <th
                            class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 w-12"
                            @if (! $showSessionColumn) x-show="expandedAll" x-cloak @endif
                        >{{ __('Session') }}</th>
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
                                    @click="$dispatch('grid-setting-click', { field: '{{ $weekCol->clickField }}' })">
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
                <tbody x-show="!expandedAll">
                    @for ($week = 0; $week < $grid->weekCount; $week++)
                        @php
                            $gridWeek = $hasPreparedWeeks ? $preparedWeeks[$week] : null;
                            $weekSessionCount = $gridWeek?->sessionCount ?? ($weekSessions[$week] ?? $grid->sessionsPerWeek);
                            $weekExpanded = $gridWeek?->expanded ?? (bool) ($expandedWeekLookup[$week] ?? false);
                            $weekLockedSessions = $gridWeek?->lockedSessions ?? ($lockedSessionsByWeek[$week] ?? []);
                            $weekHasLockedSessions = $gridWeek?->hasLockedSessions ?? in_array(true, $weekLockedSessions, true);
                            $weekShowCopyMenu = $gridWeek?->showCopyMenu ?? ! $weekHasLockedSessions;
                        @endphp
                        @if ($weekExpanded && count($grid->rows) > 0)
                            @for ($session = 0; $session < $weekSessionCount; $session++)
                                @foreach ($grid->rows as $rowIdx => $row)
                                    @php
                                        $isFirstRow = $rowIdx === 0;
                                        $sessionLocked = (bool) ($weekLockedSessions[$session] ?? false);
                                        $isLastSession = $session === $weekSessionCount - 1;
                                    @endphp
                                    <tr wire:key="mixed-expanded-w{{ $week }}-s{{ $session }}-r{{ $rowIdx }}">
                                        @if ($showWeekColumn && $session === 0 && $isFirstRow)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                                rowspan="{{ $weekSessionCount * count($grid->rows) }}">
                                                <div class="whitespace-nowrap">{!! $gridWeek?->label ?? ($weekLabels[$week] ?? 'TW' . ($week + 1)) !!}</div>
                                            </td>
                                        @endif
                                        @if ($isFirstRow)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500"
                                                rowspan="{{ count($grid->rows) }}">
                                                {{ $gridWeek?->sessionNumbers[$session] ?? ($session + 1) }}
                                            </td>
                                        @endif
                                        @if ($settingClickable)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap cursor-pointer hover:brightness-125 {{ $row->color }}"
                                                @click="$dispatch('grid-setting-click', { field: '{{ $row->clickField }}' })">
                                        @else
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap {{ $row->color }}">
                                        @endif
                                            {{ $row->label }}
                                        </td>
                                        @for ($set = 0; $set < $grid->setCount; $set++)
                                            @php
                                                $cell = $row->presentCell(
                                                    $week,
                                                    $set,
                                                    $session,
                                                    editable: $editable,
                                                    locked: $sessionLocked,
                                                    visible: ! ($row->lastSessionOnly && ! $isLastSession),
                                                );
                                            @endphp
                                            @if ($cell['editable'])
                                                <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $cell['color'] }}"
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
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $cell['value'] }}</span>
                                                    <x-training.exercise-grid-input :meta="$row->inputMeta" :value="$cell['value']" size="sm" />
                                                </td>
                                            @else
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $cell['color'] }} {{ $sessionLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                    {{ $cell['value'] }}
                                                </td>
                                            @endif
                                        @endfor
                                        @if ($isFirstRow)
                                            @foreach ($grid->weekColumns as $weekCol)
                                                @php
                                                    $weekCell = $weekCol->presentWeekCell($week, $session, editable: $editable, locked: $sessionLocked);
                                                @endphp
                                                @if ($weekCell['editable'])
                                                    <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCell['color'] }}"
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
                                                        <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $weekCell['value'] }}</span>
                                                        <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                                    </td>
                                                @else
                                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCell['color'] }} {{ $sessionLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}"
                                                        rowspan="{{ count($grid->rows) }}">
                                                        {{ $weekCell['value'] }}
                                                    </td>
                                                @endif
                                            @endforeach
                                            @if ($showCopyMenu && $session === 0)
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-2 align-middle text-center bg-zinc-50 dark:bg-zinc-800/50"
                                                    rowspan="{{ $weekSessionCount * count($grid->rows) }}">
                                                    @if ($weekShowCopyMenu)
                                                        <flux:dropdown position="bottom" align="end">
                                                            <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-0.5" />
                                                            <flux:menu>
                                                                <flux:menu.submenu heading="{{ __('Copy From') }}">
                                                                    @foreach (($gridWeek?->copyFromWeeks ?? range(0, $grid->weekCount - 1)) as $w)
                                                                        <flux:menu.item wire:click="copyWeek({{ $w }}, {{ $week }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                                    @endforeach
                                                                </flux:menu.submenu>
                                                                <flux:menu.submenu heading="{{ __('Copy To') }}">
                                                                    <flux:menu.item wire:click="copyWeekToAll({{ $week }})">{{ __('All') }}</flux:menu.item>
                                                                    @foreach (($gridWeek?->copyToWeeks ?? range(0, $grid->weekCount - 1)) as $w)
                                                                        <flux:menu.item wire:click="copyWeek({{ $week }}, {{ $w }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                                    @endforeach
                                                                </flux:menu.submenu>
                                                            </flux:menu>
                                                        </flux:dropdown>
                                                    @endif
                                                </td>
                                            @endif
                                        @endif
                                    </tr>
                                @endforeach
                            @endfor
                            @if ($sessionLabels)
                            @endif
                        @elseif (count($grid->rows) === 0)
                            <tr wire:key="collapsed-w{{ $week }}-weekonly">
                                @if ($showWeekColumn)
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center">
                                        <div class="whitespace-nowrap">{!! $gridWeek?->label ?? ($weekLabels[$week] ?? 'TW' . ($week + 1)) !!}</div>
                                        @foreach (($gridWeek?->collapsedMetaLines ?? []) as $metaLine)
                                            <div class="text-[10px] font-normal text-zinc-400 dark:text-zinc-500 whitespace-nowrap">{{ $metaLine }}</div>
                                        @endforeach
                                    </td>
                                @endif
                                @if ($showSessionColumn)
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ $gridWeek?->sessionRangeLabel }}</td>
                                @endif
                                @foreach ($grid->weekColumns as $weekCol)
                                    @php
                                        $weekCell = $weekCol->presentWeekCell($week, editable: $editable);
                                    @endphp
                                    @if ($weekCell['editable'])
                                        <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCell['color'] }}"
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
                                            <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $weekCell['value'] }}</span>
                                            <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                        </td>
                                    @else
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCell['color'] }}">
                                            {{ $weekCell['value'] }}
                                        </td>
                                    @endif
                                @endforeach
                                @if ($showCopyMenu)
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-2 align-middle text-center bg-zinc-50 dark:bg-zinc-800/50">
                                        @if ($gridWeek?->showCopyMenu ?? true)
                                            <flux:dropdown position="bottom" align="end">
                                                <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-0.5" />
                                                <flux:menu>
                                                    <flux:menu.submenu heading="{{ __('Copy From') }}">
                                                        @foreach (($gridWeek?->copyFromWeeks ?? range(0, $grid->weekCount - 1)) as $w)
                                                            <flux:menu.item wire:click="copyWeek({{ $w }}, {{ $week }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                        @endforeach
                                                    </flux:menu.submenu>
                                                    <flux:menu.submenu heading="{{ __('Copy To') }}">
                                                        <flux:menu.item wire:click="copyWeekToAll({{ $week }})">{{ __('All') }}</flux:menu.item>
                                                        @foreach (($gridWeek?->copyToWeeks ?? range(0, $grid->weekCount - 1)) as $w)
                                                            <flux:menu.item wire:click="copyWeek({{ $week }}, {{ $w }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                        @endforeach
                                                    </flux:menu.submenu>
                                                </flux:menu>
                                            </flux:dropdown>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @else
                            @foreach ($grid->rows as $rowIdx => $row)
                                <tr wire:key="collapsed-w{{ $week }}-r{{ $rowIdx }}">
                                    @if ($showWeekColumn && $rowIdx === 0)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                            rowspan="{{ count($grid->rows) }}">
                                            <div class="whitespace-nowrap">{!! $gridWeek?->label ?? ($weekLabels[$week] ?? 'TW' . ($week + 1)) !!}</div>
                                            @foreach (($gridWeek?->collapsedMetaLines ?? []) as $metaLine)
                                                <div class="text-[10px] font-normal text-zinc-400 dark:text-zinc-500 whitespace-nowrap">{{ $metaLine }}</div>
                                            @endforeach
                                        </td>
                                    @endif
                                    @if ($showSessionColumn && $rowIdx === 0)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500"
                                            rowspan="{{ count($grid->rows) }}">{{ $gridWeek?->sessionRangeLabel }}</td>
                                    @endif
                                    @if ($settingClickable)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap cursor-pointer hover:brightness-125 {{ $row->color }}"
                                            @click="$dispatch('grid-setting-click', { field: '{{ $row->clickField }}' })">
                                    @else
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap {{ $row->color }}">
                                    @endif
                                        {{ $row->label }}
                                    </td>
                                    @for ($set = 0; $set < $grid->setCount; $set++)
                                        @php
                                            $cell = $row->presentCell($week, $set, editable: $editable);
                                        @endphp
                                        @if ($cell['editable'])
                                            <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $cell['color'] }}"
                                                x-data="editable_cell"
                                                data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                data-edit-type="cell"
                                                data-field="{{ $row->field }}"
                                                data-week="{{ $week }}"
                                                data-set="{{ $set }}"
                                                data-session="{{ $weekSessionCount - 1 }}"
                                                data-apply-to-all="true"
                                                @if ($row->inputMeta && $row->inputMeta->mask)
                                                    data-mask="{{ $row->inputMeta->mask }}"
                                                @endif
                                                @click="startEditing()">
                                                <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $cell['value'] }}</span>
                                                <x-training.exercise-grid-input :meta="$row->inputMeta" :value="$cell['value']" size="sm" />
                                            </td>
                                        @else
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $cell['color'] }}">
                                                {{ $cell['value'] }}
                                            </td>
                                        @endif
                                    @endfor
                                    @if ($rowIdx === 0)
                                        @foreach ($grid->weekColumns as $weekCol)
                                            @php
                                                $weekCell = $weekCol->presentWeekCell($week, editable: $editable);
                                            @endphp
                                            @if ($weekCell['editable'])
                                                <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCell['color'] }}"
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
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $weekCell['value'] }}</span>
                                                    <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                                </td>
                                            @else
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCell['color'] }}"
                                                    rowspan="{{ count($grid->rows) }}">
                                                    {{ $weekCell['value'] }}
                                                </td>
                                            @endif
                                        @endforeach
                                        @if ($showCopyMenu)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-2 align-middle text-center bg-zinc-50 dark:bg-zinc-800/50"
                                                rowspan="{{ count($grid->rows) }}">
                                                @if ($gridWeek?->showCopyMenu ?? true)
                                                    <flux:dropdown position="bottom" align="end">
                                                        <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-0.5" />
                                                        <flux:menu>
                                                            <flux:menu.submenu heading="{{ __('Copy From') }}">
                                                                @foreach (($gridWeek?->copyFromWeeks ?? range(0, $grid->weekCount - 1)) as $w)
                                                                    <flux:menu.item wire:click="copyWeek({{ $w }}, {{ $week }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                                @endforeach
                                                            </flux:menu.submenu>
                                                            <flux:menu.submenu heading="{{ __('Copy To') }}">
                                                                <flux:menu.item wire:click="copyWeekToAll({{ $week }})">{{ __('All') }}</flux:menu.item>
                                                                @foreach (($gridWeek?->copyToWeeks ?? range(0, $grid->weekCount - 1)) as $w)
                                                                    <flux:menu.item wire:click="copyWeek({{ $week }}, {{ $w }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                                @endforeach
                                                            </flux:menu.submenu>
                                                        </flux:menu>
                                                    </flux:dropdown>
                                                @endif
                                            </td>
                                        @endif
                                    @endif
                                </tr>
                            @endforeach
                        @endif
                    @endfor
                </tbody>
                <tbody x-show="expandedAll" x-cloak>
                    @for ($week = 0; $week < $grid->weekCount; $week++)
                        @php
                            $gridWeek = $hasPreparedWeeks ? $preparedWeeks[$week] : null;
                            $weekSessionCount = $gridWeek?->sessionCount ?? ($weekSessions[$week] ?? $grid->sessionsPerWeek);
                            $weekLockedSessions = $gridWeek?->lockedSessions ?? ($lockedSessionsByWeek[$week] ?? []);
                            $weekHasLockedSessions = $gridWeek?->hasLockedSessions ?? in_array(true, $weekLockedSessions, true);
                            $weekShowCopyMenu = $gridWeek?->showCopyMenu ?? ! $weekHasLockedSessions;
                        @endphp
                        @if (count($grid->rows) === 0)
                            <tr wire:key="expanded-w{{ $week }}-weekonly">
                                @if ($showWeekColumn)
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center">
                                        <div class="whitespace-nowrap">{!! $gridWeek?->label ?? ($weekLabels[$week] ?? 'TW' . ($week + 1)) !!}</div>
                                    </td>
                                @endif
                                @if ($showSessionColumn)
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ $gridWeek?->sessionRangeLabel }}</td>
                                @endif
                                @foreach ($grid->weekColumns as $weekCol)
                                    @php
                                        $weekCell = $weekCol->presentWeekCell($week, editable: $editable, locked: $weekHasLockedSessions);
                                    @endphp
                                    @if ($weekCell['editable'])
                                            <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCell['color'] }}"
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
                                            <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $weekCell['value'] }}</span>
                                            <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                        </td>
                                    @else
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCell['color'] }}">
                                            {{ $weekCell['value'] }}
                                        </td>
                                    @endif
                                @endforeach
                                @if ($showCopyMenu)
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-2 align-middle text-center bg-zinc-50 dark:bg-zinc-800/50">
                                        @if ($weekShowCopyMenu)
                                            <flux:dropdown position="bottom" align="end">
                                                <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-0.5" />
                                                <flux:menu>
                                                    <flux:menu.submenu heading="{{ __('Copy From') }}">
                                                        @foreach (($gridWeek?->copyFromWeeks ?? range(0, $grid->weekCount - 1)) as $w)
                                                            <flux:menu.item wire:click="copyWeek({{ $w }}, {{ $week }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                        @endforeach
                                                    </flux:menu.submenu>
                                                    <flux:menu.submenu heading="{{ __('Copy To') }}">
                                                        <flux:menu.item wire:click="copyWeekToAll({{ $week }})">{{ __('All') }}</flux:menu.item>
                                                        @foreach (($gridWeek?->copyToWeeks ?? range(0, $grid->weekCount - 1)) as $w)
                                                            <flux:menu.item wire:click="copyWeek({{ $week }}, {{ $w }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                        @endforeach
                                                    </flux:menu.submenu>
                                                </flux:menu>
                                            </flux:dropdown>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @else
                        @for ($session = 0; $session < $weekSessionCount; $session++)
                            @foreach ($grid->rows as $rowIdx => $row)
                                @php
                                    $isFirstRow = $rowIdx === 0;
                                    $sessionLocked = (bool) ($weekLockedSessions[$session] ?? false);
                                @endphp
                                <tr wire:key="expanded-w{{ $week }}-s{{ $session }}-r{{ $rowIdx }}">
                                    @if ($showWeekColumn && $session === 0 && $isFirstRow)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                            rowspan="{{ $weekSessionCount * count($grid->rows) }}">
                                            <div class="whitespace-nowrap">{!! $gridWeek?->label ?? ($weekLabels[$week] ?? 'TW' . ($week + 1)) !!}</div>
                                        </td>
                                    @endif
                                    @if ($isFirstRow)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500"
                                            rowspan="{{ count($grid->rows) }}">
                                            {{ $gridWeek?->sessionNumbers[$session] ?? ($session + 1) }}
                                        </td>
                                    @endif
                                    @if ($settingClickable)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap cursor-pointer hover:brightness-125 {{ $row->color }}"
                                            @click="$dispatch('grid-setting-click', { field: '{{ $row->clickField }}' })">
                                    @else
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap {{ $row->color }}">
                                    @endif
                                        {{ $row->label }}
                                    </td>
                                    @php
                                        $isLastSession = $session === $weekSessionCount - 1;
                                    @endphp
                                    @for ($set = 0; $set < $grid->setCount; $set++)
                                        @php
                                            $cell = $row->presentCell(
                                                $week,
                                                $set,
                                                $session,
                                                editable: $editable,
                                                locked: $sessionLocked,
                                                visible: ! ($row->lastSessionOnly && ! $isLastSession),
                                            );
                                        @endphp
                                        @if ($cell['editable'])
                                                <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $cell['color'] }}"
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
                                                <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $cell['value'] }}</span>
                                                <x-training.exercise-grid-input :meta="$row->inputMeta" :value="$cell['value']" size="sm" />
                                            </td>
                                        @else
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $cell['color'] }} {{ $sessionLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                {{ $cell['value'] }}
                                            </td>
                                        @endif
                                    @endfor
                                    @if ($isFirstRow)
                                        @foreach ($grid->weekColumns as $weekCol)
                                            @php
                                                $weekCell = $weekCol->presentWeekCell($week, $session, editable: $editable, locked: $sessionLocked);
                                            @endphp
                                            @if ($weekCell['editable'])
                                                <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCell['color'] }}"
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
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $weekCell['value'] }}</span>
                                                    <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                                </td>
                                            @else
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCell['color'] }} {{ $sessionLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}"
                                                    rowspan="{{ count($grid->rows) }}">
                                                    {{ $weekCell['value'] }}
                                                </td>
                                            @endif
                                        @endforeach
                                        @if ($showCopyMenu && $session === 0)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-2 align-middle text-center bg-zinc-50 dark:bg-zinc-800/50"
                                                rowspan="{{ $weekSessionCount * count($grid->rows) }}">
                                                @if ($weekShowCopyMenu)
                                                    <flux:dropdown position="bottom" align="end">
                                                        <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-0.5" />
                                                        <flux:menu>
                                                            <flux:menu.submenu heading="{{ __('Copy From') }}">
                                                                @foreach (($gridWeek?->copyFromWeeks ?? range(0, $grid->weekCount - 1)) as $w)
                                                                    <flux:menu.item wire:click="copyWeek({{ $w }}, {{ $week }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                                @endforeach
                                                            </flux:menu.submenu>
                                                            <flux:menu.submenu heading="{{ __('Copy To') }}">
                                                                <flux:menu.item wire:click="copyWeekToAll({{ $week }})">{{ __('All') }}</flux:menu.item>
                                                                @foreach (($gridWeek?->copyToWeeks ?? range(0, $grid->weekCount - 1)) as $w)
                                                                    <flux:menu.item wire:click="copyWeek({{ $week }}, {{ $w }})">{{ __('Week') }} {{ $w + 1 }}</flux:menu.item>
                                                                @endforeach
                                                            </flux:menu.submenu>
                                                        </flux:menu>
                                                    </flux:dropdown>
                                                @endif
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
