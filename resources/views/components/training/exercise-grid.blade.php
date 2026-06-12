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
    'copyMenuOptions' => [],
    'previewMenuOptions' => [],
    'showPreview' => false,
    'showActualValues' => false,
    'valueDisplayMode' => 'planned',
    'actualCellValues' => [],
    'actualSessionValues' => [],
    'editableActualSessionsByWeek' => [],
])

@if (count($grid->rows) === 0 && count($grid->weekColumns) === 0)
    <div class="text-center text-zinc-500 dark:text-zinc-400 py-8">
        {{ __('No settings configured for this exercise.') }}
    </div>
@else
    @php
        $preparedGroups = $grid->groups ?? $grid->weeks ?? [];
        $hasPreparedGroups = count($preparedGroups) > 0;
        $showGroupColumn = $hasPreparedGroups ? ($grid->showGroupColumn ?? $grid->showWeekColumn) : ($collapseWeeks ? $grid->weekCount > 1 : true);
        $renderGroupColumn = $hasPreparedGroups ? ($grid->renderGroupColumn ?? $showGroupColumn) : $showGroupColumn;
        $showSessionColumn = true;
        $showSessionDates = (bool) ($grid->showSessionDates ?? false);
        $sessionDateLabels = $grid->sessionDateLabels ?? [];
        $groupColumnLabel = $grid->groupColumnLabel ?? __('Week');
        $splitActualColumns = $showActualValues && $valueDisplayMode === 'actual';
        $hasCopyActions = collect($copyMenuOptions)->contains(
            fn ($options): bool => ! empty($options['from'] ?? []) || ! empty($options['to'] ?? [])
        );
        $hasPreviewActions = $showPreview && collect($previewMenuOptions)->contains(
            fn ($options): bool => ! empty($options ?? [])
        );
        $showCopyColumn = ! $splitActualColumns && (bool) ($grid->showCopyMenu ?? false) && ($hasCopyActions || $hasPreviewActions);
        $baseRows = $splitActualColumns
            ? array_values(array_filter(
                $grid->rows,
                fn ($row): bool => ($row->field ?? null) !== 'oneRepMax'
            ))
            : $grid->rows;
        $displayRows = $splitActualColumns ? array_merge($baseRows, $grid->weekColumns) : $baseRows;
        $displayRowCount = count($displayRows);
        $splitSessionRowFields = $splitActualColumns ? collect($grid->weekColumns)->pluck('field')->all() : [];
        $splitSetSubcellWidthClass = 'w-[5rem] min-w-[5rem] max-w-[5rem] overflow-hidden';
        $splitSetSubcellWidthStyle = 'width: 5rem; min-width: 5rem; max-width: 5rem;';
        $splitSetHeaderWidthStyle = 'width: 10rem; min-width: 10rem;';
        $allowCoachFreeEditing = $showActualValues && $valueDisplayMode === 'actual';
        $actualDiffersFromPlanned = static fn (mixed $plannedValue, mixed $actualValue): bool => $actualValue !== null
            && $actualValue !== '-'
            && (string) $actualValue !== (string) $plannedValue;
        $formatGridValue = static fn (string $field, mixed $value): mixed => $field === 'reps'
            ? (\App\Data\Exercise\Settings\RepsSetting::formatAthleteValue($value) ?? $value)
            : $value;
    @endphp
    <div class="{{ $showHeader ? 'space-y-2 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4' : 'space-y-2' }}">
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
                            <flux:menu.item icon="pencil" wire:click="openSettingsForm">{{ __('Edit') }}</flux:menu.item>
                            <flux:menu.item icon="rotate-ccw" wire:click="resetOverrides">{{ __('Reset') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                @endif
            </div>
        @endif

        <div class="overflow-x-auto text-sm">
            <table class="border-collapse border border-zinc-300 dark:border-zinc-600 table-fixed">
                <thead>
                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                        @if ($renderGroupColumn)
                            <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-20">{{ __($groupColumnLabel) }}</th>
                        @endif
                        <th class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 w-12">{{ __('Session') }}</th>
                        @if ($displayRowCount > 0)
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
                        @if ($displayRowCount > 0)
                            @for ($i = 0; $i < $grid->setCount; $i++)
                                @php
                                    $setHeaderLabel = $grid->setCount > 1 ? $grid->setLabel . ' ' . ($i + 1) : $grid->setLabel;
                                @endphp
                                @if ($splitActualColumns)
                                    @if ($settingClickable)
                                        <th colspan="2" style="{{ $splitSetHeaderWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 whitespace-nowrap cursor-pointer hover:brightness-125"
                                            @click="$dispatch('grid-setting-click', { field: 'sets' })">
                                            <div>{{ $setHeaderLabel }}</div>
                                        </th>
                                    @else
                                        <th colspan="2" style="{{ $splitSetHeaderWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 whitespace-nowrap">
                                            <div>{{ $setHeaderLabel }}</div>
                                        </th>
                                    @endif
                                @else
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
                                @endif
                            @endfor
                        @endif
                        @if (! $splitActualColumns)
                        @foreach ($grid->weekColumns as $weekCol)
                            @if ($splitActualColumns)
                                @foreach ([__('Planned'), __('Actual')] as $subLabel)
                                    @if ($settingClickable)
                                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16 whitespace-nowrap cursor-pointer hover:brightness-125"
                                            @click="$dispatch('grid-setting-click', { field: '{{ $weekCol->clickField }}' })">
                                    @else
                                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16 whitespace-nowrap">
                                    @endif
                                            <div>{{ $weekCol->label }}</div>
                                            <div class="text-[10px] font-normal text-zinc-400 dark:text-zinc-500">{{ $subLabel }}</div>
                                        </th>
                                @endforeach
                            @else
                                @if ($settingClickable)
                                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16 whitespace-nowrap cursor-pointer hover:brightness-125"
                                        @click="$dispatch('grid-setting-click', { field: '{{ $weekCol->clickField }}' })">
                                @else
                                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16 whitespace-nowrap">
                                @endif
                                    {{ $weekCol->label }}</th>
                            @endif
                        @endforeach
                        @endif
                        @if ($showCopyColumn)
                            <th class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 w-12"></th>
                        @endif
                    </tr>
                    @if ($splitActualColumns && $displayRowCount > 0)
                        <tr class="bg-zinc-100 dark:bg-zinc-800">
                            @if ($renderGroupColumn)
                                <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-1"></th>
                            @endif
                            <th class="border border-zinc-300 dark:border-zinc-600 px-2 py-1"></th>
                            <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-1"></th>
                            @for ($i = 0; $i < $grid->setCount; $i++)
                                <th style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-1 py-1 text-[9px] font-medium uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">
                                    {{ __('Planned') }}
                                </th>
                                <th style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-1 py-1 text-[9px] font-medium uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">
                                    {{ __('Actual') }}
                                </th>
                            @endfor
                        </tr>
                    @endif
                </thead>
                <tbody>
                    @foreach ($preparedGroups as $group)
                        @php
                            $groupSessionCount = $group->sessionCount ?? 0;
                            $groupExpanded = in_array($group->index, $expandedWeeks ?? [], true) || (bool) ($group->expanded ?? false);
                            $collapsedSession = ($group->sessions ?? [])[0] ?? null;
                            $groupHasEditableSessions = collect($group->sessions ?? [])->contains(
                                fn ($session): bool => ! (bool) ($session->locked ?? false)
                            );
                            $collapsedGroupLocked = ! $groupHasEditableSessions;
                            $applyToAllByDefault = ! $groupExpanded && $groupSessionCount > 1;
                            $canToggleGroup = $renderGroupColumn && (bool) ($group->collapsible ?? false);
                        @endphp
                        @if (! $groupExpanded && $collapsedSession)
                                @php
                                    $week = $collapsedSession->weekIndex;
                                    $session = $collapsedSession->sessionIndex;
                                    $collapsedPlannedLocked = $allowCoachFreeEditing ? false : $collapsedGroupLocked;
                                @endphp
                            @if ($displayRowCount === 0)
                                <tr wire:key="collapsed-weekonly-g{{ $group->index }}-w{{ $week }}-s{{ $session }}">
                                    @php
                                        $collapsedCopyKey = ($showGroupColumn && $groupSessionCount > 1) ? 'group:' . $group->index : 'session:' . $week . ':' . $session;
                                        $collapsedCopyOptions = $copyMenuOptions[$collapsedCopyKey] ?? ['from' => [], 'to' => []];
                                    @endphp
                                    @if ($renderGroupColumn)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center">
                                            @if ($canToggleGroup)
                                                <button type="button" wire:click="toggleExpandedGroup({{ $group->index }})" class="mx-auto flex items-center justify-center gap-2 whitespace-nowrap text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                                                    <span class="font-bold text-zinc-900 dark:text-zinc-100">{!! $group->label !!}</span>
                                                    <flux:icon.chevron-down class="size-4" />
                                                </button>
                                            @else
                                                <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                                    <div>{!! $group->label !!}</div>
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500">
                                        <div>{{ $group->sessionRangeLabel }}</div>
                                        @foreach ($group->collapsedMetaLines as $line)
                                            <div>{{ $line }}</div>
                                        @endforeach
                                    </td>
                                        @foreach ($grid->weekColumns as $weekCol)
                                            @php
                                                $weekCell = $weekCol->presentWeekCell($week, $session, editable: $editable, locked: $collapsedPlannedLocked);
                                                $weekPlannedEditable = $splitActualColumns
                                                    ? ($weekCell['value'] !== '-' && ($weekCell['editable'] || $allowCoachFreeEditing))
                                                    : $weekCell['editable'];
                                                $weekPlannedColor = $weekCol->resolveCellColor($week, null, false, $session);
                                                $weekPlannedRenderKey = 'split-planned-week-collapsed-weekonly-'
                                                    .$group->index.'-'.$week.'-'.$session.'-'.$weekCol->field.'-'
                                                    .md5(json_encode([$weekCell['value'], $weekPlannedEditable, $weekPlannedColor]));
                                                $weekActualValue = $showActualValues ? data_get($actualSessionValues, $weekCol->field . '.' . $week . '.' . $session, '-') : null;
                                            $weekActualColor = $actualDiffersFromPlanned($weekCell['value'], $weekActualValue)
                                                ? $weekCol->resolveCellColor($week, null, true, $session)
                                                : $weekCell['color'];
                                            $weekActualEditable = $splitActualColumns
                                                && $weekCol->field !== 'sets'
                                                && data_get($editableActualSessionsByWeek, $week . '.' . $session, false);
                                            @endphp
                                        @if ($splitActualColumns)
                                            @if ($weekPlannedEditable)
                                                <td wire:key="{{ $weekPlannedRenderKey }}" style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $splitSetSubcellWidthClass }} {{ $weekPlannedColor }}"
                                                    x-data="editable_cell"
                                                    data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                    data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                    data-edit-type="session"
                                                    data-field="{{ $weekCol->field }}"
                                                    data-week="{{ $week }}"
                                                    data-session="{{ $session }}"
                                                    data-apply-to-all="{{ $splitActualColumns ? 'false' : ($applyToAllByDefault ? 'true' : 'false') }}"
                                                    data-provenance-kind="{{ $weekCell['provenance']?->kind ?? '' }}"
                                                    data-provenance-layer="{{ $weekCell['provenance']?->layer ?? '' }}"
                                                    @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                        data-mask="{{ $weekCol->inputMeta->mask }}"
                                                    @endif
                                                    @click="startEditing()">
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $formatGridValue($weekCol->field, $weekCell['value']) }}</span>
                                                    <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                                </td>
                                            @else
                                                <td wire:key="{{ $weekPlannedRenderKey }}" style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $splitSetSubcellWidthClass }} {{ $weekPlannedColor }} {{ $collapsedPlannedLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                    {{ $formatGridValue($weekCol->field, $weekCell['value']) }}
                                                </td>
                                            @endif
                                            @if ($weekActualEditable)
                                                <td style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $splitSetSubcellWidthClass }} {{ $weekActualColor }}"
                                                    x-data="editable_cell"
                                                    data-value-target="actual"
                                                    data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                    data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                    data-edit-type="session"
                                                    data-field="{{ $weekCol->field }}"
                                                    data-week="{{ $week }}"
                                                    data-session="{{ $session }}"
                                                    data-apply-to-all="false"
                                                    @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                        data-mask="{{ $weekCol->inputMeta->mask }}"
                                                    @endif
                                                    @click="startEditing()">
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $formatGridValue($weekCol->field, $weekActualValue ?? '-') }}</span>
                                                    <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekActualValue === '-' ? '' : $weekActualValue" size="xs" type="text" />
                                                </td>
                                            @else
                                                <td style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $splitSetSubcellWidthClass }} {{ $weekActualColor }} {{ $collapsedGroupLocked || $weekActualValue === null || $weekActualValue === '-' ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                    {{ $formatGridValue($weekCol->field, $weekActualValue ?? '-') }}
                                                </td>
                                            @endif
                                        @elseif ($weekCell['editable'])
                                            <td wire:key="planned-week-collapsed-weekonly-{{ $group->index }}-{{ $week }}-{{ $session }}-{{ $weekCol->field }}-{{ md5(json_encode([$weekCell['value'], $weekCell['editable'], $weekCell['color']])) }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCell['color'] }}"
                                                x-data="editable_cell"
                                                data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                data-edit-type="session"
                                                data-field="{{ $weekCol->field }}"
                                                data-week="{{ $week }}"
                                                data-session="{{ $session }}"
                                                data-apply-to-all="{{ $applyToAllByDefault ? 'true' : 'false' }}"
                                                data-provenance-kind="{{ $weekCell['provenance']?->kind ?? '' }}"
                                                data-provenance-layer="{{ $weekCell['provenance']?->layer ?? '' }}"
                                                @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                    data-mask="{{ $weekCol->inputMeta->mask }}"
                                                @endif
                                                @click="startEditing()">
                                                <span x-show="!editing" class="block px-3 py-2 cursor-pointer">
                                                    @include('components.training.partials.planned-actual-value', [
                                                        'plannedValue' => $weekCell['value'],
                                                        'actualValue' => $weekActualValue,
                                                        'field' => $weekCol->field,
                                                        'mode' => $showActualValues ? $valueDisplayMode : 'planned',
                                                    ])
                                                </span>
                                                <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                            </td>
                                        @else
                                            <td wire:key="planned-week-collapsed-weekonly-{{ $group->index }}-{{ $week }}-{{ $session }}-{{ $weekCol->field }}-{{ md5(json_encode([$weekCell['value'], $weekCell['editable'], $weekCell['color']])) }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCell['color'] }} {{ $collapsedPlannedLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                @include('components.training.partials.planned-actual-value', [
                                                    'plannedValue' => $weekCell['value'],
                                                    'actualValue' => $weekActualValue,
                                                    'field' => $weekCol->field,
                                                    'mode' => $showActualValues ? $valueDisplayMode : 'planned',
                                                ])
                                            </td>
                                        @endif
                                    @endforeach
                                    @if ($showCopyColumn)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-1 align-middle text-center">
                                            @if (! empty($collapsedCopyOptions['from'] ?? []) || ! empty($collapsedCopyOptions['to'] ?? []) || ($showPreview && ! empty($previewMenuOptions[$collapsedCopyKey] ?? [])))
                                                <flux:dropdown position="bottom" align="end">
                                                    <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-1" />
                                                    <flux:menu>
                                                        @if (! empty($collapsedCopyOptions['from'] ?? []))
                                                            <flux:menu.submenu :heading="__('Copy From')">
                                                                @foreach (($collapsedCopyOptions['from'] ?? []) as $option)
                                                                    <flux:menu.item wire:click="copyDisplayBucket('{{ $option['source'] }}', '{{ $option['target'] }}')">
                                                                        {{ __($option['label']) }}
                                                                    </flux:menu.item>
                                                                @endforeach
                                                            </flux:menu.submenu>
                                                        @endif
                                                        @if (! empty($collapsedCopyOptions['to'] ?? []))
                                                            <flux:menu.submenu :heading="__('Copy To')">
                                                                @if (filled($collapsedCopyOptions['toAll'] ?? null))
                                                                    <flux:menu.item wire:click="copyDisplayBucketToAll('{{ $collapsedCopyOptions['toAll']['source'] }}')">
                                                                        {{ __($collapsedCopyOptions['toAll']['label']) }}
                                                                    </flux:menu.item>
                                                                    <flux:menu.separator />
                                                                @endif
                                                                @foreach (($collapsedCopyOptions['to'] ?? []) as $option)
                                                                    <flux:menu.item wire:click="copyDisplayBucket('{{ $option['source'] }}', '{{ $option['target'] }}')">
                                                                        {{ __($option['label']) }}
                                                                    </flux:menu.item>
                                                                @endforeach
                                                            </flux:menu.submenu>
                                                        @endif
                                                        @if ($showPreview)
                                                            @include('components.training.partials.preview-menu-item', [
                                                                'previewSessions' => $previewMenuOptions[$collapsedCopyKey] ?? [],
                                                            ])
                                                        @endif
                                                        <flux:menu.separator />
                                                        <flux:menu.item icon="rotate-ccw" wire:click="resetDisplayBucket('{{ $collapsedCopyKey }}')">
                                                            {{ __('Reset') }}
                                                        </flux:menu.item>
                                                    </flux:menu>
                                                </flux:dropdown>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @else
                                    @foreach ($displayRows as $rowIdx => $row)
                                        @php
                                            $isFirstRow = $rowIdx === 0;
                                            $isSessionScopedRow = $splitActualColumns && in_array($row->field, $splitSessionRowFields, true);
                                            $collapsedCopyKey = ($showGroupColumn && $groupSessionCount > 1) ? 'group:' . $group->index : 'session:' . $week . ':' . $session;
                                            $collapsedCopyOptions = $copyMenuOptions[$collapsedCopyKey] ?? ['from' => [], 'to' => []];
                                            $collapsedPlannedLocked = $allowCoachFreeEditing ? false : $collapsedGroupLocked;
                                        @endphp
                                    <tr wire:key="collapsed-g{{ $group->index }}-w{{ $week }}-s{{ $session }}-r{{ $rowIdx }}">
                                        @if ($renderGroupColumn && $isFirstRow)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                                rowspan="{{ $displayRowCount }}">
                                                @if ($canToggleGroup)
                                                    <button type="button" wire:click="toggleExpandedGroup({{ $group->index }})" class="mx-auto flex items-center justify-center gap-2 whitespace-nowrap text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                                                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{!! $group->label !!}</span>
                                                        <flux:icon.chevron-down class="size-4" />
                                                    </button>
                                                @else
                                                    <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                                        <div>{!! $group->label !!}</div>
                                                    </div>
                                                @endif
                                            </td>
                                        @endif
                                        @if ($isFirstRow)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500"
                                                rowspan="{{ $displayRowCount }}">
                                                <div>{{ $group->sessionRangeLabel }}</div>
                                                @foreach ($group->collapsedMetaLines as $line)
                                                    <div>{{ $line }}</div>
                                                @endforeach
                                            </td>
                                        @endif
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap {{ $row->color }}">
                                            {{ $row->label }}
                                        </td>
                                        @for ($set = 0; $set < $grid->setCount; $set++)
                                            @php
                                                $cell = $isSessionScopedRow
                                                    ? $row->presentWeekCell($week, $session, editable: $editable, locked: $collapsedPlannedLocked)
                                                    : $row->presentCell(
                                                        $week,
                                                        $set,
                                                        $session,
                                                        editable: $editable,
                                                        locked: $collapsedPlannedLocked,
                                                        visible: true,
                                                    );
                                                $cellPlannedColor = $row->resolveCellColor($week, $isSessionScopedRow ? null : $set, false, $session);
                                                $cellPlannedEditable = $splitActualColumns
                                                    ? ($cell['value'] !== '-' && ($cell['editable'] || $allowCoachFreeEditing))
                                                    : $cell['editable'];
                                                $cellPlannedRenderKey = 'split-planned-cell-collapsed-'
                                                    .$group->index.'-'.$week.'-'.$session.'-'.$row->field.'-'.$set.'-'
                                                    .md5(json_encode([$cell['value'], $cellPlannedEditable, $cellPlannedColor]));
                                                $cellActualValue = $showActualValues
                                                    ? ($isSessionScopedRow
                                                        ? data_get($actualSessionValues, $row->field . '.' . $week . '.' . $session, '-')
                                                        : data_get($actualCellValues, $row->field . '.' . $week . '.' . $session . '.' . $set, '-'))
                                                    : null;
                                                $cellActualColor = $actualDiffersFromPlanned($cell['value'], $cellActualValue)
                                                    ? $row->resolveCellColor($week, $isSessionScopedRow ? null : $set, true, $session)
                                                    : $cell['color'];
                                                $cellActualEditable = $splitActualColumns && data_get($editableActualSessionsByWeek, $week . '.' . $session, false);
                                            @endphp
                                            @if ($splitActualColumns)
                                                @if ($cellPlannedEditable)
                                                    <td wire:key="{{ $cellPlannedRenderKey }}" style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $splitSetSubcellWidthClass }} {{ $cellPlannedColor }}"
                                                        x-data="editable_cell"
                                                        data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                        data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                        data-edit-type="{{ $isSessionScopedRow ? 'session' : 'cell' }}"
                                                        data-field="{{ $row->field }}"
                                                        data-week="{{ $week }}"
                                                        @if (! $isSessionScopedRow)
                                                            data-set="{{ $set }}"
                                                        @endif
                                                        data-session="{{ $session }}"
                                                        data-apply-to-all="{{ $splitActualColumns ? 'false' : ($applyToAllByDefault ? 'true' : 'false') }}"
                                                        data-provenance-kind="{{ $cell['provenance']?->kind ?? '' }}"
                                                        data-provenance-layer="{{ $cell['provenance']?->layer ?? '' }}"
                                                        @if ($row->inputMeta && $row->inputMeta->mask)
                                                            data-mask="{{ $row->inputMeta->mask }}"
                                                        @endif
                                                        @click="startEditing()">
                                                        <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $formatGridValue($row->field, $cell['value']) }}</span>
                                                        <x-training.exercise-grid-input :meta="$row->inputMeta" :value="$cell['value']" size="sm" />
                                                    </td>
                                                @else
                                                    <td wire:key="{{ $cellPlannedRenderKey }}" style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $splitSetSubcellWidthClass }} {{ $cellPlannedColor }} {{ $collapsedPlannedLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                        {{ $formatGridValue($row->field, $cell['value']) }}
                                                    </td>
                                                @endif
                                                @if ($cellActualEditable)
                                                    <td style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $splitSetSubcellWidthClass }} {{ $cellActualColor }}"
                                                        x-data="editable_cell"
                                                        data-value-target="actual"
                                                        data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                        data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                        data-edit-type="{{ $isSessionScopedRow ? 'session' : 'cell' }}"
                                                        data-field="{{ $row->field }}"
                                                        data-week="{{ $week }}"
                                                        @if (! $isSessionScopedRow)
                                                            data-set="{{ $set }}"
                                                        @endif
                                                        data-session="{{ $session }}"
                                                        data-apply-to-all="false"
                                                        @if ($row->inputMeta && $row->inputMeta->mask)
                                                            data-mask="{{ $row->inputMeta->mask }}"
                                                        @endif
                                                        @click="startEditing()">
                                                        <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $formatGridValue($row->field, $cellActualValue ?? '-') }}</span>
                                                        <x-training.exercise-grid-input :meta="$row->inputMeta" :value="$cellActualValue === '-' ? '' : $cellActualValue" size="sm" />
                                                    </td>
                                                @else
                                                    <td style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $splitSetSubcellWidthClass }} {{ $cellActualColor }} {{ $collapsedGroupLocked || $cellActualValue === null || $cellActualValue === '-' ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                        {{ $formatGridValue($row->field, $cellActualValue ?? '-') }}
                                                    </td>
                                                @endif
                                            @elseif ($cell['editable'])
                                                <td wire:key="{{ $cellPlannedRenderKey }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $cell['color'] }}"
                                                    x-data="editable_cell"
                                                    data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                    data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                    data-edit-type="cell"
                                                    data-field="{{ $row->field }}"
                                                    data-week="{{ $week }}"
                                                    data-set="{{ $set }}"
                                                    data-session="{{ $session }}"
                                                    data-apply-to-all="{{ $splitActualColumns ? 'false' : ($applyToAllByDefault ? 'true' : 'false') }}"
                                                    data-provenance-kind="{{ $cell['provenance']?->kind ?? '' }}"
                                                    data-provenance-layer="{{ $cell['provenance']?->layer ?? '' }}"
                                                    @if ($row->inputMeta && $row->inputMeta->mask)
                                                        data-mask="{{ $row->inputMeta->mask }}"
                                                    @endif
                                                    @click="startEditing()">
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">
                                                        @include('components.training.partials.planned-actual-value', [
                                                            'plannedValue' => $cell['value'],
                                                            'actualValue' => $cellActualValue,
                                                            'field' => $row->field,
                                                            'mode' => $showActualValues ? $valueDisplayMode : 'planned',
                                                        ])
                                                    </span>
                                                    <x-training.exercise-grid-input :meta="$row->inputMeta" :value="$cell['value']" size="sm" />
                                                </td>
                                            @else
                                                <td wire:key="{{ $cellPlannedRenderKey }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $cell['color'] }} {{ $collapsedPlannedLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                    @include('components.training.partials.planned-actual-value', [
                                                        'plannedValue' => $cell['value'],
                                                        'actualValue' => $cellActualValue,
                                                        'field' => $row->field,
                                                        'mode' => $showActualValues ? $valueDisplayMode : 'planned',
                                                    ])
                                                </td>
                                            @endif
                                        @endfor
                                        @if ($isFirstRow && ! $splitActualColumns)
                                            @foreach ($grid->weekColumns as $weekCol)
                                                @php
                                                    $weekCell = $weekCol->presentWeekCell($week, $session, editable: $editable, locked: $collapsedPlannedLocked);
                                                @endphp
                                                @if ($weekCell['editable'])
                                                    <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCell['color'] }}"
                                                        rowspan="{{ count($grid->rows) }}"
                                                        x-data="editable_cell"
                                                        data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                        data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                        data-edit-type="session"
                                                        data-field="{{ $weekCol->field }}"
                                                        data-week="{{ $week }}"
                                                        data-session="{{ $session }}"
                                                        data-apply-to-all="{{ $splitActualColumns ? 'false' : ($applyToAllByDefault ? 'true' : 'false') }}"
                                                        data-provenance-kind="{{ $weekCell['provenance']?->kind ?? '' }}"
                                                        data-provenance-layer="{{ $weekCell['provenance']?->layer ?? '' }}"
                                                        @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                            data-mask="{{ $weekCol->inputMeta->mask }}"
                                                        @endif
                                                        @click="startEditing()">
                                                        <span x-show="!editing" class="block px-3 py-2 cursor-pointer">
                                                            @include('components.training.partials.planned-actual-value', [
                                                                'plannedValue' => $weekCell['value'],
                                                                'actualValue' => $showActualValues ? data_get($actualSessionValues, $weekCol->field . '.' . $week . '.' . $session, '-') : null,
                                                                'field' => $weekCol->field,
                                                                'mode' => $showActualValues ? $valueDisplayMode : 'planned',
                                                            ])
                                                        </span>
                                                        <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                                    </td>
                                                @else
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCell['color'] }} {{ $collapsedPlannedLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}"
                                                    rowspan="{{ count($grid->rows) }}">
                                                    @include('components.training.partials.planned-actual-value', [
                                                        'plannedValue' => $weekCell['value'],
                                                        'actualValue' => $showActualValues ? data_get($actualSessionValues, $weekCol->field . '.' . $week . '.' . $session, '-') : null,
                                                        'field' => $weekCol->field,
                                                        'mode' => $showActualValues ? $valueDisplayMode : 'planned',
                                                    ])
                                                </td>
                                            @endif
                                            @endforeach
                                            @if ($showCopyColumn)
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-1 align-middle text-center"
                                                    rowspan="{{ count($grid->rows) }}">
                                                    @if (! empty($collapsedCopyOptions['from'] ?? []) || ! empty($collapsedCopyOptions['to'] ?? []) || ($showPreview && ! empty($previewMenuOptions[$collapsedCopyKey] ?? [])))
                                                        <flux:dropdown position="bottom" align="end">
                                                            <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-1" />
                                                            <flux:menu>
                                                                @if (! empty($collapsedCopyOptions['from'] ?? []))
                                                                    <flux:menu.submenu :heading="__('Copy From')">
                                                                        @foreach (($collapsedCopyOptions['from'] ?? []) as $option)
                                                                            <flux:menu.item wire:click="copyDisplayBucket('{{ $option['source'] }}', '{{ $option['target'] }}')">
                                                                                {{ __($option['label']) }}
                                                                            </flux:menu.item>
                                                                        @endforeach
                                                                    </flux:menu.submenu>
                                                                @endif
                                                                @if (! empty($collapsedCopyOptions['to'] ?? []))
                                                                    <flux:menu.submenu :heading="__('Copy To')">
                                                                        @if (filled($collapsedCopyOptions['toAll'] ?? null))
                                                                            <flux:menu.item wire:click="copyDisplayBucketToAll('{{ $collapsedCopyOptions['toAll']['source'] }}')">
                                                                                {{ __($collapsedCopyOptions['toAll']['label']) }}
                                                                            </flux:menu.item>
                                                                            <flux:menu.separator />
                                                                        @endif
                                                                        @foreach (($collapsedCopyOptions['to'] ?? []) as $option)
                                                                            <flux:menu.item wire:click="copyDisplayBucket('{{ $option['source'] }}', '{{ $option['target'] }}')">
                                                                                {{ __($option['label']) }}
                                                                            </flux:menu.item>
                                                                        @endforeach
                                                                    </flux:menu.submenu>
                                                                @endif
                                                                @if ($showPreview)
                                                                    @include('components.training.partials.preview-menu-item', [
                                                                        'previewSessions' => $previewMenuOptions[$collapsedCopyKey] ?? [],
                                                                    ])
                                                                @endif
                                                                <flux:menu.separator />
                                                                <flux:menu.item icon="rotate-ccw" wire:click="resetDisplayBucket('{{ $collapsedCopyKey }}')">
                                                                    {{ __('Reset') }}
                                                                </flux:menu.item>
                                                            </flux:menu>
                                                        </flux:dropdown>
                                                    @endif
                                                </td>
                                            @endif
                                        @endif
                                    </tr>
                                @endforeach
                            @endif
                        @elseif ($displayRowCount === 0)
                            @foreach (($group->sessions ?? []) as $sessionEntry)
                                @php
                                    $week = $sessionEntry->weekIndex;
                                    $session = $sessionEntry->sessionIndex;
                                    $sessionLocked = (bool) ($sessionEntry->locked ?? false);
                                    $plannedSessionLocked = $allowCoachFreeEditing ? false : $sessionLocked;
                                    $sessionCopyKey = 'session:' . $week . ':' . $session;
                                    $sessionCopyOptions = $copyMenuOptions[$sessionCopyKey] ?? ['from' => [], 'to' => []];
                                @endphp
                                <tr wire:key="weekonly-g{{ $group->index }}-w{{ $week }}-s{{ $session }}">
                                        @if ($renderGroupColumn && $loop->first)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                                rowspan="{{ $groupSessionCount }}">
                                            @if ($canToggleGroup)
                                                <button type="button" wire:click="toggleExpandedGroup({{ $group->index }})" class="mx-auto flex items-center justify-center gap-2 whitespace-nowrap text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                                                    <span class="font-bold text-zinc-900 dark:text-zinc-100">{!! $group->label !!}</span>
                                                    <flux:icon.chevron-up class="size-4" />
                                                </button>
                                            @else
                                                <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                                    <div>{!! $group->label !!}</div>
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500">
                                        <div>{{ $sessionEntry->sessionNumber }}</div>
                                        @if ($showSessionDates && filled($sessionDateLabels[$week][$session] ?? null))
                                            <div class="mt-1 text-[10px] font-normal text-zinc-500 dark:text-zinc-400">{{ $sessionDateLabels[$week][$session] }}</div>
                                        @endif
                                    </td>
                                        @foreach ($grid->weekColumns as $weekCol)
                                            @php
                                                $weekCell = $weekCol->presentWeekCell($week, $session, editable: $editable, locked: $plannedSessionLocked);
                                                $weekPlannedEditable = $splitActualColumns
                                                    ? ($weekCell['value'] !== '-' && ($weekCell['editable'] || $allowCoachFreeEditing))
                                                    : $weekCell['editable'];
                                                $weekPlannedColor = $weekCol->resolveCellColor($week, null, false, $session);
                                                $weekPlannedRenderKey = 'split-planned-week-expanded-weekonly-'
                                                    .$group->index.'-'.$week.'-'.$session.'-'.$weekCol->field.'-'
                                                    .md5(json_encode([$weekCell['value'], $weekPlannedEditable, $weekPlannedColor]));
                                                $weekActualValue = $showActualValues ? data_get($actualSessionValues, $weekCol->field . '.' . $week . '.' . $session, '-') : null;
                                            $weekActualColor = $actualDiffersFromPlanned($weekCell['value'], $weekActualValue)
                                                ? $weekCol->resolveCellColor($week, null, true, $session)
                                                : $weekCell['color'];
                                            $weekActualEditable = $splitActualColumns
                                                && $weekCol->field !== 'sets'
                                                && data_get($editableActualSessionsByWeek, $week . '.' . $session, false);
                                        @endphp
                                        @if ($splitActualColumns)
                                            @if ($weekPlannedEditable)
                                                <td wire:key="{{ $weekPlannedRenderKey }}" style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $splitSetSubcellWidthClass }} {{ $weekPlannedColor }}"
                                                    x-data="editable_cell"
                                                    data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                    data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                    data-edit-type="session"
                                                    data-field="{{ $weekCol->field }}"
                                                    data-week="{{ $week }}"
                                                    data-session="{{ $session }}"
                                                    data-apply-to-all="{{ $splitActualColumns ? 'false' : ($applyToAllByDefault ? 'true' : 'false') }}"
                                                    data-provenance-kind="{{ $weekCell['provenance']?->kind ?? '' }}"
                                                    data-provenance-layer="{{ $weekCell['provenance']?->layer ?? '' }}"
                                                    @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                        data-mask="{{ $weekCol->inputMeta->mask }}"
                                                    @endif
                                                    @click="startEditing()">
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $formatGridValue($weekCol->field, $weekCell['value']) }}</span>
                                                    <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                                </td>
                                            @else
                                                <td wire:key="{{ $weekPlannedRenderKey }}" style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $splitSetSubcellWidthClass }} {{ $weekPlannedColor }} {{ $plannedSessionLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                    {{ $formatGridValue($weekCol->field, $weekCell['value']) }}
                                                </td>
                                            @endif
                                            @if ($weekActualEditable)
                                                <td style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $splitSetSubcellWidthClass }} {{ $weekActualColor }}"
                                                    x-data="editable_cell"
                                                    data-value-target="actual"
                                                    data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                    data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                    data-edit-type="session"
                                                    data-field="{{ $weekCol->field }}"
                                                    data-week="{{ $week }}"
                                                    data-session="{{ $session }}"
                                                    data-apply-to-all="false"
                                                    @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                        data-mask="{{ $weekCol->inputMeta->mask }}"
                                                    @endif
                                                    @click="startEditing()">
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $formatGridValue($weekCol->field, $weekActualValue ?? '-') }}</span>
                                                    <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekActualValue === '-' ? '' : $weekActualValue" size="xs" type="text" />
                                                </td>
                                            @else
                                                <td style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $splitSetSubcellWidthClass }} {{ $weekActualColor }} {{ $sessionLocked || $weekActualValue === null || $weekActualValue === '-' ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                    {{ $formatGridValue($weekCol->field, $weekActualValue ?? '-') }}
                                                </td>
                                            @endif
                                        @elseif ($weekCell['editable'])
                                            <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCell['color'] }}"
                                                x-data="editable_cell"
                                                data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                data-edit-type="session"
                                                data-field="{{ $weekCol->field }}"
                                                data-week="{{ $week }}"
                                                data-session="{{ $session }}"
                                                data-apply-to-all="{{ $applyToAllByDefault ? 'true' : 'false' }}"
                                                data-provenance-kind="{{ $weekCell['provenance']?->kind ?? '' }}"
                                                data-provenance-layer="{{ $weekCell['provenance']?->layer ?? '' }}"
                                                @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                    data-mask="{{ $weekCol->inputMeta->mask }}"
                                                @endif
                                                @click="startEditing()">
                                                <span x-show="!editing" class="block px-3 py-2 cursor-pointer">
                                                    @include('components.training.partials.planned-actual-value', [
                                                        'plannedValue' => $weekCell['value'],
                                                        'actualValue' => $weekActualValue,
                                                        'field' => $weekCol->field,
                                                        'mode' => $showActualValues ? $valueDisplayMode : 'planned',
                                                    ])
                                                </span>
                                                <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                            </td>
                                        @else
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCell['color'] }} {{ $plannedSessionLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                @include('components.training.partials.planned-actual-value', [
                                                    'plannedValue' => $weekCell['value'],
                                                    'actualValue' => $weekActualValue,
                                                    'field' => $weekCol->field,
                                                    'mode' => $showActualValues ? $valueDisplayMode : 'planned',
                                                ])
                                            </td>
                                        @endif
                                    @endforeach
                                    @if ($showCopyColumn)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-1 align-middle text-center">
                                            @if (! empty($sessionCopyOptions['from'] ?? []) || ! empty($sessionCopyOptions['to'] ?? []) || ($showPreview && ! empty($previewMenuOptions[$sessionCopyKey] ?? [])))
                                                <flux:dropdown position="bottom" align="end">
                                                    <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-1" />
                                                    <flux:menu>
                                                        @if (! empty($sessionCopyOptions['from'] ?? []))
                                                            <flux:menu.submenu :heading="__('Copy From')">
                                                                @foreach (($sessionCopyOptions['from'] ?? []) as $option)
                                                                    <flux:menu.item wire:click="copyDisplayBucket('{{ $option['source'] }}', '{{ $option['target'] }}')">
                                                                        {{ __($option['label']) }}
                                                                    </flux:menu.item>
                                                                @endforeach
                                                            </flux:menu.submenu>
                                                        @endif
                                                        @if (! empty($sessionCopyOptions['to'] ?? []))
                                                            <flux:menu.submenu :heading="__('Copy To')">
                                                                @if (filled($sessionCopyOptions['toAll'] ?? null))
                                                                    <flux:menu.item wire:click="copyDisplayBucketToAll('{{ $sessionCopyOptions['toAll']['source'] }}')">
                                                                        {{ __($sessionCopyOptions['toAll']['label']) }}
                                                                    </flux:menu.item>
                                                                    <flux:menu.separator />
                                                                @endif
                                                                @foreach (($sessionCopyOptions['to'] ?? []) as $option)
                                                                    <flux:menu.item wire:click="copyDisplayBucket('{{ $option['source'] }}', '{{ $option['target'] }}')">
                                                                        {{ __($option['label']) }}
                                                                    </flux:menu.item>
                                                                @endforeach
                                                            </flux:menu.submenu>
                                                        @endif
                                                        @if ($showPreview)
                                                            @include('components.training.partials.preview-menu-item', [
                                                                'previewSessions' => $previewMenuOptions[$sessionCopyKey] ?? [],
                                                            ])
                                                        @endif
                                                        <flux:menu.separator />
                                                        <flux:menu.item icon="rotate-ccw" wire:click="resetDisplayBucket('{{ $sessionCopyKey }}')">
                                                            {{ __('Reset') }}
                                                        </flux:menu.item>
                                                    </flux:menu>
                                                </flux:dropdown>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        @else
                            @foreach (($group->sessions ?? []) as $sessionEntry)
                                @foreach ($displayRows as $rowIdx => $row)
                                    @php
                                        $week = $sessionEntry->weekIndex;
                                        $session = $sessionEntry->sessionIndex;
                                        $isFirstRow = $rowIdx === 0;
                                        $isSessionScopedRow = $splitActualColumns && in_array($row->field, $splitSessionRowFields, true);
                                        $sessionLocked = (bool) ($sessionEntry->locked ?? false);
                                        $plannedSessionLocked = $allowCoachFreeEditing ? false : $sessionLocked;
                                        $isLastSession = $loop->parent->index === $groupSessionCount - 1;
                                        $sessionCopyKey = 'session:' . $week . ':' . $session;
                                        $sessionCopyOptions = $copyMenuOptions[$sessionCopyKey] ?? ['from' => [], 'to' => []];
                                    @endphp
                                    <tr wire:key="expanded-g{{ $group->index }}-w{{ $week }}-s{{ $session }}-r{{ $rowIdx }}">
                                        @if ($renderGroupColumn && $loop->parent->first && $isFirstRow)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                                rowspan="{{ $groupSessionCount * $displayRowCount }}">
                                                @if ($canToggleGroup)
                                                    <button type="button" wire:click="toggleExpandedGroup({{ $group->index }})" class="mx-auto flex items-center justify-center gap-2 whitespace-nowrap text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                                                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{!! $group->label !!}</span>
                                                        <flux:icon.chevron-up class="size-4" />
                                                    </button>
                                                @else
                                                    <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                                        <div>{!! $group->label !!}</div>
                                                    </div>
                                                @endif
                                            </td>
                                        @endif
                                        @if ($isFirstRow)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500"
                                                rowspan="{{ $displayRowCount }}">
                                                <div>{{ $sessionEntry->sessionNumber }}</div>
                                                @if ($showSessionDates && filled($sessionDateLabels[$week][$session] ?? null))
                                                    <div class="mt-1 text-[10px] font-normal text-zinc-500 dark:text-zinc-400">{{ $sessionDateLabels[$week][$session] }}</div>
                                                @endif
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
                                                $cell = $isSessionScopedRow
                                                    ? $row->presentWeekCell($week, $session, editable: $editable, locked: $plannedSessionLocked)
                                                    : $row->presentCell(
                                                        $week,
                                                        $set,
                                                        $session,
                                                        editable: $editable,
                                                        locked: $plannedSessionLocked,
                                                        visible: ! ($row->lastSessionOnly && ! $isLastSession),
                                                    );
                                                $cellPlannedColor = $row->resolveCellColor($week, $isSessionScopedRow ? null : $set, false, $session);
                                                $cellPlannedEditable = $splitActualColumns
                                                    ? ($cell['value'] !== '-' && ($cell['editable'] || $allowCoachFreeEditing))
                                                    : $cell['editable'];
                                                $cellPlannedRenderKey = 'split-planned-cell-expanded-'
                                                    .$group->index.'-'.$week.'-'.$session.'-'.$row->field.'-'.$set.'-'
                                                    .md5(json_encode([$cell['value'], $cellPlannedEditable, $cellPlannedColor]));
                                                $cellActualValue = $showActualValues
                                                    ? ($isSessionScopedRow
                                                        ? data_get($actualSessionValues, $row->field . '.' . $week . '.' . $session, '-')
                                                        : data_get($actualCellValues, $row->field . '.' . $week . '.' . $session . '.' . $set, '-'))
                                                    : null;
                                                $cellActualColor = $actualDiffersFromPlanned($cell['value'], $cellActualValue)
                                                    ? $row->resolveCellColor($week, $isSessionScopedRow ? null : $set, true, $session)
                                                    : $cell['color'];
                                                $cellActualEditable = $splitActualColumns && data_get($editableActualSessionsByWeek, $week . '.' . $session, false);
                                            @endphp
                                            @if ($splitActualColumns)
                                                @if ($cellPlannedEditable)
                                                    <td wire:key="{{ $cellPlannedRenderKey }}" style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $splitSetSubcellWidthClass }} {{ $cellPlannedColor }}"
                                                        x-data="editable_cell"
                                                        data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                        data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                        data-edit-type="{{ $isSessionScopedRow ? 'session' : 'cell' }}"
                                                        data-field="{{ $row->field }}"
                                                        data-week="{{ $week }}"
                                                        @if (! $isSessionScopedRow)
                                                            data-set="{{ $set }}"
                                                        @endif
                                                        data-session="{{ $session }}"
                                                        data-apply-to-all="{{ $splitActualColumns ? 'false' : ($applyToAllByDefault ? 'true' : 'false') }}"
                                                        data-provenance-kind="{{ $cell['provenance']?->kind ?? '' }}"
                                                        data-provenance-layer="{{ $cell['provenance']?->layer ?? '' }}"
                                                        @if ($row->inputMeta && $row->inputMeta->mask)
                                                            data-mask="{{ $row->inputMeta->mask }}"
                                                        @endif
                                                        @click="startEditing()">
                                                        <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $formatGridValue($row->field, $cell['value']) }}</span>
                                                        <x-training.exercise-grid-input :meta="$row->inputMeta" :value="$cell['value']" size="sm" />
                                                    </td>
                                                @else
                                                    <td wire:key="{{ $cellPlannedRenderKey }}" style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $splitSetSubcellWidthClass }} {{ $cellPlannedColor }} {{ $plannedSessionLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                        {{ $formatGridValue($row->field, $cell['value']) }}
                                                    </td>
                                                @endif
                                                @if ($cellActualEditable)
                                                    <td style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $splitSetSubcellWidthClass }} {{ $cellActualColor }}"
                                                        x-data="editable_cell"
                                                        data-value-target="actual"
                                                        data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                        data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                        data-edit-type="{{ $isSessionScopedRow ? 'session' : 'cell' }}"
                                                        data-field="{{ $row->field }}"
                                                        data-week="{{ $week }}"
                                                        @if (! $isSessionScopedRow)
                                                            data-set="{{ $set }}"
                                                        @endif
                                                        data-session="{{ $session }}"
                                                        data-apply-to-all="false"
                                                        @if ($row->inputMeta && $row->inputMeta->mask)
                                                            data-mask="{{ $row->inputMeta->mask }}"
                                                        @endif
                                                        @click="startEditing()">
                                                        <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $formatGridValue($row->field, $cellActualValue ?? '-') }}</span>
                                                        <x-training.exercise-grid-input :meta="$row->inputMeta" :value="$cellActualValue === '-' ? '' : $cellActualValue" size="sm" />
                                                    </td>
                                                @else
                                                    <td style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $splitSetSubcellWidthClass }} {{ $cellActualColor }} {{ $sessionLocked || $cellActualValue === null || $cellActualValue === '-' ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                        {{ $formatGridValue($row->field, $cellActualValue ?? '-') }}
                                                    </td>
                                                @endif
                                            @elseif ($cell['editable'])
                                                <td wire:key="{{ $cellPlannedRenderKey }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $cell['color'] }}"
                                                    x-data="editable_cell"
                                                    data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                    data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                    data-edit-type="cell"
                                                    data-field="{{ $row->field }}"
                                                    data-week="{{ $week }}"
                                                    data-set="{{ $set }}"
                                                    data-session="{{ $session }}"
                                                    data-apply-to-all="{{ $applyToAllByDefault ? 'true' : 'false' }}"
                                                    data-provenance-kind="{{ $cell['provenance']?->kind ?? '' }}"
                                                    data-provenance-layer="{{ $cell['provenance']?->layer ?? '' }}"
                                                    @if ($row->inputMeta && $row->inputMeta->mask)
                                                        data-mask="{{ $row->inputMeta->mask }}"
                                                    @endif
                                                    @click="startEditing()">
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">
                                                        @include('components.training.partials.planned-actual-value', [
                                                            'plannedValue' => $cell['value'],
                                                            'actualValue' => $cellActualValue,
                                                            'field' => $row->field,
                                                            'mode' => $showActualValues ? $valueDisplayMode : 'planned',
                                                        ])
                                                    </span>
                                                    <x-training.exercise-grid-input :meta="$row->inputMeta" :value="$cell['value']" size="sm" />
                                                </td>
                                            @else
                                                <td wire:key="{{ $cellPlannedRenderKey }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $cell['color'] }} {{ $plannedSessionLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                    @include('components.training.partials.planned-actual-value', [
                                                        'plannedValue' => $cell['value'],
                                                        'actualValue' => $cellActualValue,
                                                        'field' => $row->field,
                                                        'mode' => $showActualValues ? $valueDisplayMode : 'planned',
                                                    ])
                                                </td>
                                            @endif
                                        @endfor
                                        @if ($isFirstRow && ! $splitActualColumns)
                                            @foreach ($grid->weekColumns as $weekCol)
                                                @php
                                                    $weekCell = $weekCol->presentWeekCell($week, $session, editable: $editable, locked: $plannedSessionLocked);
                                                    $weekActualValue = $showActualValues ? data_get($actualSessionValues, $weekCol->field . '.' . $week . '.' . $session, '-') : null;
                                                    $weekActualEditable = $splitActualColumns && data_get($editableActualSessionsByWeek, $week . '.' . $session, false);
                                                @endphp
                                                @if ($splitActualColumns)
                                                    @if ($weekCell['editable'])
                                                        <td style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $splitSetSubcellWidthClass }} {{ $weekCell['color'] }}"
                                                            rowspan="{{ count($grid->rows) }}"
                                                            x-data="editable_cell"
                                                            data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                            data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                            data-edit-type="session"
                                                            data-field="{{ $weekCol->field }}"
                                                            data-week="{{ $week }}"
                                                            data-session="{{ $session }}"
                                                            data-apply-to-all="{{ $splitActualColumns ? 'false' : ($applyToAllByDefault ? 'true' : 'false') }}"
                                                            data-provenance-kind="{{ $weekCell['provenance']?->kind ?? '' }}"
                                                            data-provenance-layer="{{ $weekCell['provenance']?->layer ?? '' }}"
                                                            @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                                data-mask="{{ $weekCol->inputMeta->mask }}"
                                                            @endif
                                                            @click="startEditing()">
                                                            <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $formatGridValue($weekCol->field, $weekCell['value']) }}</span>
                                                            <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                                        </td>
                                                    @else
                                                        <td style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $splitSetSubcellWidthClass }} {{ $weekCell['color'] }} {{ $plannedSessionLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}"
                                                            rowspan="{{ count($grid->rows) }}">
                                                            {{ $formatGridValue($weekCol->field, $weekCell['value']) }}
                                                        </td>
                                                    @endif
                                                    @if ($weekActualEditable)
                                                        <td style="{{ $splitSetSubcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $splitSetSubcellWidthClass }} {{ $weekCell['color'] }}"
                                                            rowspan="{{ count($grid->rows) }}"
                                                            x-data="editable_cell"
                                                            data-value-target="actual"
                                                            data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                            data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                            data-edit-type="session"
                                                            data-field="{{ $weekCol->field }}"
                                                            data-week="{{ $week }}"
                                                            data-session="{{ $session }}"
                                                            data-apply-to-all="false"
                                                            @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                                data-mask="{{ $weekCol->inputMeta->mask }}"
                                                            @endif
                                                            @click="startEditing()">
                                                            <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $formatGridValue($weekCol->field, $weekActualValue ?? '-') }}</span>
                                                            <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekActualValue === '-' ? '' : $weekActualValue" size="xs" type="text" />
                                                        </td>
                                                    @else
                                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCell['color'] }} {{ $sessionLocked || $weekActualValue === null || $weekActualValue === '-' ? 'text-zinc-400 dark:text-zinc-500' : '' }}"
                                                            rowspan="{{ count($grid->rows) }}">
                                                            {{ $formatGridValue($weekCol->field, $weekActualValue ?? '-') }}
                                                        </td>
                                                    @endif
                                        @elseif ($weekCell['editable'])
                                            <td wire:key="planned-week-expanded-firstrow-{{ $group->index }}-{{ $week }}-{{ $session }}-{{ $weekCol->field }}-{{ md5(json_encode([$weekCell['value'], $weekCell['editable'], $weekCell['color']])) }}" class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $weekCell['color'] }}"
                                                rowspan="{{ count($grid->rows) }}"
                                                x-data="editable_cell"
                                                        data-msg-invalid-number="{{ __('Please enter a valid number') }}"
                                                        data-msg-invalid-value="{{ __('Please enter a valid value') }}"
                                                        data-edit-type="session"
                                                        data-field="{{ $weekCol->field }}"
                                                        data-week="{{ $week }}"
                                                        data-session="{{ $session }}"
                                                        data-apply-to-all="{{ $applyToAllByDefault ? 'true' : 'false' }}"
                                                        data-provenance-kind="{{ $weekCell['provenance']?->kind ?? '' }}"
                                                        data-provenance-layer="{{ $weekCell['provenance']?->layer ?? '' }}"
                                                        @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                            data-mask="{{ $weekCol->inputMeta->mask }}"
                                                        @endif
                                                        @click="startEditing()">
                                                        <span x-show="!editing" class="block px-3 py-2 cursor-pointer">
                                                            @include('components.training.partials.planned-actual-value', [
                                                                'plannedValue' => $weekCell['value'],
                                                                'actualValue' => $weekActualValue,
                                                                'field' => $weekCol->field,
                                                                'mode' => $showActualValues ? $valueDisplayMode : 'planned',
                                                            ])
                                                        </span>
                                                        <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                                    </td>
                                                @else
                                                    <td wire:key="planned-week-expanded-firstrow-{{ $group->index }}-{{ $week }}-{{ $session }}-{{ $weekCol->field }}-{{ md5(json_encode([$weekCell['value'], $weekCell['editable'], $weekCell['color']])) }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCell['color'] }} {{ $plannedSessionLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}"
                                                        rowspan="{{ count($grid->rows) }}">
                                                        @include('components.training.partials.planned-actual-value', [
                                                            'plannedValue' => $weekCell['value'],
                                                            'actualValue' => $weekActualValue,
                                                            'field' => $weekCol->field,
                                                            'mode' => $showActualValues ? $valueDisplayMode : 'planned',
                                                        ])
                                                    </td>
                                                @endif
                                            @endforeach
                                            @if ($showCopyColumn)
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-1 align-middle text-center"
                                                    rowspan="{{ count($grid->rows) }}">
                                                    @if (! empty($sessionCopyOptions['from'] ?? []) || ! empty($sessionCopyOptions['to'] ?? []) || ($showPreview && ! empty($previewMenuOptions[$sessionCopyKey] ?? [])))
                                                        <flux:dropdown position="bottom" align="end">
                                                            <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-1" />
                                                            <flux:menu>
                                                                @if (! empty($sessionCopyOptions['from'] ?? []))
                                                                    <flux:menu.submenu :heading="__('Copy From')">
                                                                        @foreach (($sessionCopyOptions['from'] ?? []) as $option)
                                                                            <flux:menu.item wire:click="copyDisplayBucket('{{ $option['source'] }}', '{{ $option['target'] }}')">
                                                                                {{ __($option['label']) }}
                                                                            </flux:menu.item>
                                                                        @endforeach
                                                                    </flux:menu.submenu>
                                                                @endif
                                                                @if (! empty($sessionCopyOptions['to'] ?? []))
                                                                    <flux:menu.submenu :heading="__('Copy To')">
                                                                        @if (filled($sessionCopyOptions['toAll'] ?? null))
                                                                            <flux:menu.item wire:click="copyDisplayBucketToAll('{{ $sessionCopyOptions['toAll']['source'] }}')">
                                                                                {{ __($sessionCopyOptions['toAll']['label']) }}
                                                                            </flux:menu.item>
                                                                            <flux:menu.separator />
                                                                        @endif
                                                                        @foreach (($sessionCopyOptions['to'] ?? []) as $option)
                                                                            <flux:menu.item wire:click="copyDisplayBucket('{{ $option['source'] }}', '{{ $option['target'] }}')">
                                                                                {{ __($option['label']) }}
                                                                            </flux:menu.item>
                                                                        @endforeach
                                                                    </flux:menu.submenu>
                                                                @endif
                                                                @if ($showPreview)
                                                                    @include('components.training.partials.preview-menu-item', [
                                                                        'previewSessions' => $previewMenuOptions[$sessionCopyKey] ?? [],
                                                                    ])
                                                                @endif
                                                                <flux:menu.separator />
                                                                <flux:menu.item icon="rotate-ccw" wire:click="resetDisplayBucket('{{ $sessionCopyKey }}')">
                                                                    {{ __('Reset') }}
                                                                </flux:menu.item>
                                                            </flux:menu>
                                                        </flux:dropdown>
                                                    @endif
                                                </td>
                                            @endif
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
