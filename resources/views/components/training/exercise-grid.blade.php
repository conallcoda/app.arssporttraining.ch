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
        $preparedGroups = $grid->groups ?? $grid->weeks ?? [];
        $hasPreparedGroups = count($preparedGroups) > 0;
        $showGroupColumn = $hasPreparedGroups ? ($grid->showGroupColumn ?? $grid->showWeekColumn) : ($collapseWeeks ? $grid->weekCount > 1 : true);
        $showSessionColumn = true;
        $groupColumnLabel = $grid->groupColumnLabel ?? __('Week');
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
                            <flux:menu.item icon="pencil" wire:click="openSettingsForm">{{ __('Edit Settings') }}</flux:menu.item>
                            <flux:menu.item icon="rotate-ccw" wire:click="resetOverrides">{{ __('Reset Overrides') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                @endif
            </div>
        @endif

        <div class="overflow-x-auto text-sm">
            <table class="border-collapse border border-zinc-300 dark:border-zinc-600 table-fixed">
                <thead>
                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                        @if ($showGroupColumn)
                            <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-20">{{ __($groupColumnLabel) }}</th>
                        @endif
                        <th class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 w-12">{{ __('Session') }}</th>
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
                    </tr>
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
                            $applyToAllByDefault = $groupSessionCount > 1;
                        @endphp
                        @if (! $groupExpanded && $collapsedSession)
                            @php
                                $week = $collapsedSession->weekIndex;
                                $session = $collapsedSession->sessionIndex;
                            @endphp
                            @if (count($grid->rows) === 0)
                                <tr wire:key="collapsed-weekonly-g{{ $group->index }}-w{{ $week }}-s{{ $session }}">
                                    @if ($showGroupColumn)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center">
                                            <div class="whitespace-nowrap">{!! $group->label !!}</div>
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
                                            $weekCell = $weekCol->presentWeekCell($week, $session, editable: $editable, locked: $collapsedGroupLocked);
                                        @endphp
                                        @if ($weekCell['editable'])
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
                                                <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $weekCell['value'] }}</span>
                                                <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                            </td>
                                        @else
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCell['color'] }} {{ $collapsedGroupLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                {{ $weekCell['value'] }}
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @else
                                @foreach ($grid->rows as $rowIdx => $row)
                                    @php
                                        $isFirstRow = $rowIdx === 0;
                                    @endphp
                                    <tr wire:key="collapsed-g{{ $group->index }}-w{{ $week }}-s{{ $session }}-r{{ $rowIdx }}">
                                        @if ($showGroupColumn && $isFirstRow)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                                rowspan="{{ count($grid->rows) }}">
                                                <div class="whitespace-nowrap">{!! $group->label !!}</div>
                                            </td>
                                        @endif
                                        @if ($isFirstRow)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500"
                                                rowspan="{{ count($grid->rows) }}">
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
                                                $cell = $row->presentCell(
                                                    $week,
                                                    $set,
                                                    $session,
                                                    editable: $editable,
                                                    locked: $collapsedGroupLocked,
                                                    visible: true,
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
                                                    data-apply-to-all="{{ $applyToAllByDefault ? 'true' : 'false' }}"
                                                    data-provenance-kind="{{ $cell['provenance']?->kind ?? '' }}"
                                                    data-provenance-layer="{{ $cell['provenance']?->layer ?? '' }}"
                                                    @if ($row->inputMeta && $row->inputMeta->mask)
                                                        data-mask="{{ $row->inputMeta->mask }}"
                                                    @endif
                                                    @click="startEditing()">
                                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $cell['value'] }}</span>
                                                    <x-training.exercise-grid-input :meta="$row->inputMeta" :value="$cell['value']" size="sm" />
                                                </td>
                                            @else
                                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $cell['color'] }} {{ $collapsedGroupLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                    {{ $cell['value'] }}
                                                </td>
                                            @endif
                                        @endfor
                                        @if ($isFirstRow)
                                            @foreach ($grid->weekColumns as $weekCol)
                                                @php
                                                    $weekCell = $weekCol->presentWeekCell($week, $session, editable: $editable, locked: $collapsedGroupLocked);
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
                                                        data-apply-to-all="{{ $applyToAllByDefault ? 'true' : 'false' }}"
                                                        data-provenance-kind="{{ $weekCell['provenance']?->kind ?? '' }}"
                                                        data-provenance-layer="{{ $weekCell['provenance']?->layer ?? '' }}"
                                                        @if ($weekCol->inputMeta && $weekCol->inputMeta->mask)
                                                            data-mask="{{ $weekCol->inputMeta->mask }}"
                                                        @endif
                                                        @click="startEditing()">
                                                        <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $weekCell['value'] }}</span>
                                                        <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                                    </td>
                                                @else
                                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCell['color'] }} {{ $collapsedGroupLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}"
                                                        rowspan="{{ count($grid->rows) }}">
                                                        {{ $weekCell['value'] }}
                                                    </td>
                                                @endif
                                            @endforeach
                                        @endif
                                    </tr>
                                @endforeach
                            @endif
                        @elseif (count($grid->rows) === 0)
                            @foreach (($group->sessions ?? []) as $sessionEntry)
                                @php
                                    $week = $sessionEntry->weekIndex;
                                    $session = $sessionEntry->sessionIndex;
                                    $sessionLocked = (bool) ($sessionEntry->locked ?? false);
                                @endphp
                                <tr wire:key="weekonly-g{{ $group->index }}-w{{ $week }}-s{{ $session }}">
                                    @if ($showGroupColumn && $loop->first)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                            rowspan="{{ $groupSessionCount }}">
                                            <div class="whitespace-nowrap">{!! $group->label !!}</div>
                                        </td>
                                    @endif
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500">
                                        {{ $sessionEntry->sessionNumber }}
                                    </td>
                                    @foreach ($grid->weekColumns as $weekCol)
                                        @php
                                            $weekCell = $weekCol->presentWeekCell($week, $session, editable: $editable, locked: $sessionLocked);
                                        @endphp
                                        @if ($weekCell['editable'])
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
                                                <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $weekCell['value'] }}</span>
                                                <x-training.exercise-grid-input :meta="$weekCol->inputMeta" :value="$weekCell['value']" size="xs" type="text" />
                                            </td>
                                        @else
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle {{ $weekCell['color'] }} {{ $sessionLocked ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                                {{ $weekCell['value'] }}
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        @else
                            @foreach (($group->sessions ?? []) as $sessionEntry)
                                @foreach ($grid->rows as $rowIdx => $row)
                                    @php
                                        $week = $sessionEntry->weekIndex;
                                        $session = $sessionEntry->sessionIndex;
                                        $isFirstRow = $rowIdx === 0;
                                        $sessionLocked = (bool) ($sessionEntry->locked ?? false);
                                        $isLastSession = $loop->parent->index === $groupSessionCount - 1;
                                    @endphp
                                    <tr wire:key="expanded-g{{ $group->index }}-w{{ $week }}-s{{ $session }}-r{{ $rowIdx }}">
                                        @if ($showGroupColumn && $loop->parent->first && $isFirstRow)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                                rowspan="{{ $groupSessionCount * count($grid->rows) }}">
                                                <div class="whitespace-nowrap">{!! $group->label !!}</div>
                                            </td>
                                        @endif
                                        @if ($isFirstRow)
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500"
                                                rowspan="{{ count($grid->rows) }}">
                                                {{ $sessionEntry->sessionNumber }}
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
                                                    data-apply-to-all="{{ $applyToAllByDefault ? 'true' : 'false' }}"
                                                    data-provenance-kind="{{ $cell['provenance']?->kind ?? '' }}"
                                                    data-provenance-layer="{{ $cell['provenance']?->layer ?? '' }}"
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
