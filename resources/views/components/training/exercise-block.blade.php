@props([
    'block',
    'exercise',
    'exerciseId',
    'config',
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
    $setCount = 0;
    foreach ($weeks as $week) {
        foreach ($week->sessions as $session) {
            $setCount = max($setCount, count($session->sets));
        }
    }
    $setCount = $setCount ?: 4;

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

    $cellClasses = [
        'reps' => 'bg-blue-50 dark:bg-blue-900/20',
        'reps_override' => 'bg-blue-200 dark:bg-blue-700/40',
        'weight' => 'bg-green-50 dark:bg-green-900/20',
        'weight_override' => 'bg-green-200 dark:bg-green-700/40',
        'oneRepMax' => 'bg-orange-50 dark:bg-orange-900/20',
        'tut' => 'bg-zinc-50 dark:bg-zinc-800/50',
        'tut_override' => 'bg-zinc-200 dark:bg-zinc-700/70',
        'rest' => 'bg-zinc-50 dark:bg-zinc-800/50',
        'rest_override' => 'bg-zinc-200 dark:bg-zinc-700/70',
    ];

    $hasOverride = function ($weekIndex, $sessionIndex, $setIndex, $field) use ($userSpecificCellOverrides) {
        $key = "w{$weekIndex}-s{$sessionIndex}-set{$setIndex}";
        return isset($userSpecificCellOverrides[$key][$field]);
    };

    $hasWeekOverride = function ($weekIndex, $field) use ($userSpecificWeekOverrides) {
        $key = "w{$weekIndex}";
        return isset($userSpecificWeekOverrides[$key][$field]);
    };

    $getWeekTut = function ($weekIndex) use ($weekOverrides, $config) {
        $key = "w{$weekIndex}";
        return $weekOverrides[$key]['tut'] ?? $config['tut'] ?? '3010';
    };

    $getWeekRest = function ($weekIndex) use ($weekOverrides, $config) {
        $key = "w{$weekIndex}";
        return $weekOverrides[$key]['rest'] ?? $config['rest'] ?? 30;
    };

    $exerciseTut = $config['tut'] ?? '3010';
    $exerciseRest = $config['rest'] ?? 30;

    $getCalendarWeek = function ($weekIndex) use ($startDate) {
        if (!$startDate) {
            return null;
        }
        $date = \Carbon\Carbon::parse($startDate)->addWeeks($weekIndex);
        return $date->year . ' - W' . $date->isoWeek();
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
        @if ($block)
            @php
                $modifier = $config['oneRepMaxModifier'] ?? 100;
                $modifierDiff = $modifier - 100;
                $targetPercent = $config['target'] ?? 0;
            @endphp
            <div class="flex items-center gap-2 text-sm mt-1">
                <span class="font-medium text-amber-600 dark:text-amber-400">{{ $block->config->starting1RM }}kg</span>
                @if ($modifierDiff !== 0)
                    <span
                        class="text-zinc-500 dark:text-zinc-400">({{ $modifierDiff > 0 ? '+' : '' }}{{ $modifierDiff }}%)</span>
                @endif
                <flux:icon.arrow-right class="size-4 text-zinc-400" />
                <span class="font-medium text-green-600 dark:text-green-400">{{ $block->config->target1RM }}kg</span>
                <span class="text-zinc-500 dark:text-zinc-400">(+{{ $targetPercent }}%)</span>
            </div>
        @endif
    </div>

    @php
        $modalName = "exercise-settings-{$exerciseId}";
        $badges = [
            [
                'field' => 'target',
                'label' => '+' . $config['target'] . '%',
                'hasOverride' => $config['hasTargetOverride'],
            ],
            [
                'field' => 'startingReps',
                'label' => $config['startingReps'] . ' reps',
                'hasOverride' => $config['hasStartingRepsOverride'],
            ],
            ['field' => 'sets', 'label' => $config['sets'] . ' sets', 'hasOverride' => $config['hasSetsOverride']],
            ['field' => 'tut', 'label' => $config['tut'], 'hasOverride' => $config['hasTutOverride'] ?? false],
            ['field' => 'rest', 'label' => $config['rest'] . 's', 'hasOverride' => $config['hasRestOverride'] ?? false],
        ];
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
            target: {{ $config['target'] }},
            startingReps: {{ $config['startingReps'] }},
            sets: {{ $config['sets'] }},
            tut: '{{ $config['tut'] }}',
            rest: {{ $config['rest'] }},
            save() {
                $wire.updateExerciseOverride({{ $exerciseId }}, 'target', parseFloat(this.target));
                $wire.updateExerciseOverride({{ $exerciseId }}, 'startingReps', parseInt(this.startingReps));
                $wire.updateExerciseOverride({{ $exerciseId }}, 'sets', parseInt(this.sets));
                $wire.updateExerciseOverride({{ $exerciseId }}, 'tut', this.tut);
                $wire.updateExerciseOverride({{ $exerciseId }}, 'rest', parseInt(this.rest));
                $flux.modal('{{ $modalName }}').close();
            }
        }">
            <flux:heading size="lg">{{ $exercise->name }}</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Target</flux:label>
                    <flux:input.group>
                        <flux:input x-model="target" type="number" min="0" step="0.5" data-field="target" />
                        <flux:input.group.suffix>%</flux:input.group.suffix>
                    </flux:input.group>
                </flux:field>

                <flux:field>
                    <flux:label>Starting Reps</flux:label>
                    <flux:input.group>
                        <flux:input x-model="startingReps" type="number" min="1" max="25" step="1"
                            data-field="startingReps" />
                        <flux:input.group.suffix>reps</flux:input.group.suffix>
                    </flux:input.group>
                </flux:field>

                <flux:field>
                    <flux:label>Sets</flux:label>
                    <flux:input.group>
                        <flux:input x-model="sets" type="number" min="1" max="6" step="1"
                            data-field="sets" />
                        <flux:input.group.suffix>sets</flux:input.group.suffix>
                    </flux:input.group>
                </flux:field>

                <flux:field>
                    <flux:label>Time Under Tension</flux:label>
                    <flux:input x-model="tut" type="text" maxlength="4" data-field="tut" placeholder="3010" />
                    <flux:description>4 digits: eccentric, pause, concentric, pause</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Rest</flux:label>
                    <flux:input.group>
                        <flux:input x-model="rest" type="number" min="0" max="300" step="5"
                            data-field="rest" />
                        <flux:input.group.suffix>seconds</flux:input.group.suffix>
                    </flux:input.group>
                </flux:field>
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
                            <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16">Set
                                {{ $i + 1 }}</th>
                        @endfor
                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-24">TUT</th>
                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 w-16">Rest</th>
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
                            <tr>
                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                    rowspan="3">
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
                                        rowspan="3">
                                        {{ $sessionCount }}
                                    </td>
                                @endif
                                @for ($i = 0; $i < $setCount; $i++)
                                    @if (isset($session->sets[$i]))
                                        @php $repsOverridden = $hasOverride($weekIndex, $sessionIndex, $i, 'reps'); @endphp
                                        <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $repsOverridden ? $cellClasses['reps_override'] : $cellClasses['reps'] }}"
                                            x-data="{ editing: false, editValue: '' }"
                                            @click="editing = true; editValue = ''; $nextTick(() => $refs.input.focus())">
                                            <span x-show="!editing"
                                                class="block px-3 py-2 cursor-pointer">{{ $session->sets[$i]->reps ?? '-' }}</span>
                                            <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                                type="number" min="1" step="1"
                                                class="w-full h-full px-2 py-1.5 text-center text-sm bg-transparent border-0 focus:ring-0"
                                                placeholder="{{ $session->sets[$i]->reps ?? '' }}"
                                                @blur="editing = false; if (editValue !== '') $wire.updateCellOverride({{ $exerciseId }}, {{ $weekIndex }}, {{ $i }}, 'reps', parseInt(editValue))"
                                                @keydown.enter="$event.target.blur()"
                                                @keydown.escape="editing = false" />
                                        </td>
                                    @else
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center"
                                            rowspan="3"></td>
                                    @endif
                                @endfor
                                @php
                                    $weekTut = $getWeekTut($weekIndex);
                                    $weekRest = $getWeekRest($weekIndex);
                                    $tutOverridden = $hasWeekOverride($weekIndex, 'tut');
                                    $restOverridden = $hasWeekOverride($weekIndex, 'rest');
                                @endphp
                                <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $tutOverridden ? $cellClasses['tut_override'] : $cellClasses['tut'] }}"
                                    rowspan="3"
                                    x-data="{ editing: false, editValue: '' }"
                                    @click="editing = true; editValue = ''; $nextTick(() => $refs.input.focus())">
                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $weekTut }}</span>
                                    <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                        type="text" maxlength="4"
                                        class="w-full h-full px-2 py-1.5 text-center text-xs bg-transparent border-0 focus:ring-0"
                                        placeholder="{{ $weekTut }}"
                                        @blur="editing = false; if (editValue !== '') $wire.updateWeekOverride({{ $exerciseId }}, {{ $weekIndex }}, 'tut', editValue)"
                                        @keydown.enter="$event.target.blur()"
                                        @keydown.escape="editing = false" />
                                </td>
                                <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $restOverridden ? $cellClasses['rest_override'] : $cellClasses['rest'] }}"
                                    rowspan="3"
                                    x-data="{ editing: false, editValue: '' }"
                                    @click="editing = true; editValue = ''; $nextTick(() => $refs.input.focus())">
                                    <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $weekRest }}s</span>
                                    <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                        type="number" min="0" max="300" step="5"
                                        class="w-full h-full px-2 py-1.5 text-center text-xs bg-transparent border-0 focus:ring-0"
                                        placeholder="{{ $weekRest }}"
                                        @blur="editing = false; if (editValue !== '') $wire.updateWeekOverride({{ $exerciseId }}, {{ $weekIndex }}, 'rest', parseInt(editValue))"
                                        @keydown.enter="$event.target.blur()"
                                        @keydown.escape="editing = false" />
                                </td>
                            </tr>
                            <tr>
                                @for ($i = 0; $i < $setCount; $i++)
                                    @if (isset($session->sets[$i]))
                                        @php $weightOverridden = $hasOverride($weekIndex, $sessionIndex, $i, 'weight'); @endphp
                                        @if ($isDefaultUser)
                                            <td
                                                class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $cellClasses['weight'] }}">
                                                {{ $session->sets[$i]->weight ?? '-' }}
                                            </td>
                                        @else
                                            <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $weightOverridden ? $cellClasses['weight_override'] : $cellClasses['weight'] }}"
                                                x-data="{ editing: false, editValue: '' }"
                                                @click="editing = true; editValue = ''; $nextTick(() => $refs.input.focus())">
                                                <span x-show="!editing"
                                                    class="block px-3 py-2 cursor-pointer">{{ $session->sets[$i]->weight ?? '-' }}</span>
                                                <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                                    type="number" min="0" step="0.5"
                                                    class="w-full h-full px-2 py-1.5 text-center text-sm bg-transparent border-0 focus:ring-0"
                                                    placeholder="{{ $session->sets[$i]->weight ?? '' }}"
                                                    @blur="editing = false; if (editValue !== '') $wire.updateCellOverride({{ $exerciseId }}, {{ $weekIndex }}, {{ $i }}, 'weight', parseFloat(editValue))"
                                                    @keydown.enter="$event.target.blur()"
                                                    @keydown.escape="editing = false" />
                                            </td>
                                        @endif
                                    @endif
                                @endfor
                            </tr>
                            <tr>
                                @for ($i = 0; $i < $setCount; $i++)
                                    @if (isset($session->sets[$i]))
                                        <td
                                            class="border border-zinc-300 dark:border-zinc-600 px-3 py-1 text-center text-xs text-zinc-500 dark:text-zinc-400 {{ $cellClasses['oneRepMax'] }}">
                                            {{ $session->sets[$i]->oneRepMax ?? '-' }}
                                        </td>
                                    @endif
                                @endfor
                            </tr>
                        @else
                            @foreach ($week->sessions as $sessionIndex => $session)
                                @php $isFirstSession = $sessionIndex === 0; @endphp
                                <tr>
                                    @if ($isFirstSession)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle text-center"
                                            rowspan="{{ $sessionCount * 3 }}">
                                            <div>TW{{ $weekIndex + 1 }}</div>
                                            @if ($getCalendarWeek($weekIndex))
                                                <div class="text-[10px] font-normal text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                                                    {{ $getCalendarWeek($weekIndex) }}</div>
                                            @endif
                                        </td>
                                    @endif
                                    @if ($anyWeekNotMerged)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center bg-zinc-100 dark:bg-zinc-700/50 text-xs font-medium"
                                            rowspan="3">
                                            S{{ $sessionIndex + 1 }}
                                        </td>
                                    @endif
                                    @for ($i = 0; $i < $setCount; $i++)
                                        @if (isset($session->sets[$i]))
                                            @php $repsOverridden = $hasOverride($weekIndex, $sessionIndex, $i, 'reps'); @endphp
                                            <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $repsOverridden ? $cellClasses['reps_override'] : $cellClasses['reps'] }}"
                                                x-data="{ editing: false, editValue: '' }"
                                                @click="editing = true; editValue = ''; $nextTick(() => $refs.input.focus())">
                                                <span x-show="!editing"
                                                    class="block px-3 py-2 cursor-pointer">{{ $session->sets[$i]->reps ?? '-' }}</span>
                                                <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                                    type="number" min="1" step="1"
                                                    class="w-full h-full px-2 py-1.5 text-center text-sm bg-transparent border-0 focus:ring-0"
                                                    placeholder="{{ $session->sets[$i]->reps ?? '' }}"
                                                    @blur="editing = false; if (editValue !== '') $wire.updateCellOverride({{ $exerciseId }}, {{ $weekIndex }}, {{ $i }}, 'reps', parseInt(editValue))"
                                                    @keydown.enter="$event.target.blur()"
                                                    @keydown.escape="editing = false" />
                                            </td>
                                        @else
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center"
                                                rowspan="3"></td>
                                        @endif
                                    @endfor
                                    @if ($isFirstSession)
                                        @php
                                            $weekTut = $getWeekTut($weekIndex);
                                            $weekRest = $getWeekRest($weekIndex);
                                            $tutOverridden = $hasWeekOverride($weekIndex, 'tut');
                                            $restOverridden = $hasWeekOverride($weekIndex, 'rest');
                                        @endphp
                                        <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $tutOverridden ? $cellClasses['tut_override'] : $cellClasses['tut'] }}"
                                            rowspan="{{ $sessionCount * 3 }}"
                                            x-data="{ editing: false, editValue: '' }"
                                            @click="editing = true; editValue = ''; $nextTick(() => $refs.input.focus())">
                                            <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $weekTut }}</span>
                                            <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                                type="text" maxlength="4"
                                                class="w-full h-full px-2 py-1.5 text-center text-xs bg-transparent border-0 focus:ring-0"
                                                placeholder="{{ $weekTut }}"
                                                @blur="editing = false; if (editValue !== '') $wire.updateWeekOverride({{ $exerciseId }}, {{ $weekIndex }}, 'tut', editValue)"
                                                @keydown.enter="$event.target.blur()"
                                                @keydown.escape="editing = false" />
                                        </td>
                                        <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center text-xs align-middle {{ $restOverridden ? $cellClasses['rest_override'] : $cellClasses['rest'] }}"
                                            rowspan="{{ $sessionCount * 3 }}"
                                            x-data="{ editing: false, editValue: '' }"
                                            @click="editing = true; editValue = ''; $nextTick(() => $refs.input.focus())">
                                            <span x-show="!editing" class="block px-3 py-2 cursor-pointer">{{ $weekRest }}s</span>
                                            <input x-show="editing" x-cloak x-ref="input" x-model="editValue"
                                                type="number" min="0" max="300" step="5"
                                                class="w-full h-full px-2 py-1.5 text-center text-xs bg-transparent border-0 focus:ring-0"
                                                placeholder="{{ $weekRest }}"
                                                @blur="editing = false; if (editValue !== '') $wire.updateWeekOverride({{ $exerciseId }}, {{ $weekIndex }}, 'rest', parseInt(editValue))"
                                                @keydown.enter="$event.target.blur()"
                                                @keydown.escape="editing = false" />
                                        </td>
                                    @endif
                                </tr>
                                <tr>
                                    @for ($i = 0; $i < $setCount; $i++)
                                        @if (isset($session->sets[$i]))
                                            @php $weightOverridden = $hasOverride($weekIndex, $sessionIndex, $i, 'weight'); @endphp
                                            @if ($isDefaultUser)
                                                <td
                                                    class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $cellClasses['weight'] }}">
                                                    {{ $session->sets[$i]->weight ?? '-' }}
                                                </td>
                                            @else
                                                <td class="border border-zinc-300 dark:border-zinc-600 p-0 text-center {{ $weightOverridden ? $cellClasses['weight_override'] : $cellClasses['weight'] }}"
                                                    x-data="{ editing: false, editValue: '' }"
                                                    @click="editing = true; editValue = ''; $nextTick(() => $refs.input.focus())">
                                                    <span x-show="!editing"
                                                        class="block px-3 py-2 cursor-pointer">{{ $session->sets[$i]->weight ?? '-' }}</span>
                                                    <input x-show="editing" x-cloak x-ref="input"
                                                        x-model="editValue" type="number" min="0"
                                                        step="0.5"
                                                        class="w-full h-full px-2 py-1.5 text-center text-sm bg-transparent border-0 focus:ring-0"
                                                        placeholder="{{ $session->sets[$i]->weight ?? '' }}"
                                                        @blur="editing = false; if (editValue !== '') $wire.updateCellOverride({{ $exerciseId }}, {{ $weekIndex }}, {{ $i }}, 'weight', parseFloat(editValue))"
                                                        @keydown.enter="$event.target.blur()"
                                                        @keydown.escape="editing = false" />
                                                </td>
                                            @endif
                                        @endif
                                    @endfor
                                </tr>
                                <tr>
                                    @for ($i = 0; $i < $setCount; $i++)
                                        @if (isset($session->sets[$i]))
                                            <td
                                                class="border border-zinc-300 dark:border-zinc-600 px-3 py-1 text-center text-xs text-zinc-500 dark:text-zinc-400 {{ $cellClasses['oneRepMax'] }}">
                                                {{ $session->sets[$i]->oneRepMax ?? '-' }}
                                            </td>
                                        @endif
                                    @endfor
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="text-center text-zinc-500 dark:text-zinc-400 py-4">
            Enter measured data to generate training plan.
        </div>
    @endif
</div>
