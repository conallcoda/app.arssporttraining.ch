@props([
    'block',
    'exercise',
    'exerciseId',
    'config',
    'mergeIdenticalSessions' => true,
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
            return false;
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
        'weight' => 'bg-green-50 dark:bg-green-900/20',
        'oneRepMax' => 'bg-orange-50 dark:bg-orange-900/20',
    ];
@endphp

<div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
    <div class="mb-4">
        <flux:heading size="lg">{{ $exercise->name }}</flux:heading>
        @if ($block)
            @php
                $modifier = $config['oneRepMaxModifier'] ?? 100;
                $modifierDiff = $modifier - 100;
                $targetPercent = $config['target'] ?? 0;
            @endphp
            <div class="flex items-center gap-2 text-sm mt-1">
                <span class="font-medium text-amber-600 dark:text-amber-400">{{ number_format($block->config->starting1RM, 1) }}kg</span>
                @if ($modifierDiff !== 0)
                    <span class="text-zinc-500 dark:text-zinc-400">({{ $modifierDiff > 0 ? '+' : '' }}{{ $modifierDiff }}%)</span>
                @endif
                <flux:icon.arrow-right class="size-4 text-zinc-400" />
                <span class="font-medium text-green-600 dark:text-green-400">{{ number_format($block->config->target1RM, 1) }}kg</span>
                <span class="text-zinc-500 dark:text-zinc-400">(+{{ $targetPercent }}%)</span>
            </div>
        @endif
    </div>

    @php
        $modalName = "exercise-settings-{$exerciseId}";
        $badges = [
            ['field' => 'target', 'label' => '+' . $config['target'] . '%', 'hasOverride' => $config['hasTargetOverride']],
            ['field' => 'startingReps', 'label' => $config['startingReps'] . ' reps', 'hasOverride' => $config['hasStartingRepsOverride']],
            ['field' => 'sets', 'label' => $config['sets'] . ' sets', 'hasOverride' => $config['hasSetsOverride']],
        ];
    @endphp

    <div class="flex flex-wrap gap-1 mb-4">
        @foreach ($badges as $badge)
            <flux:badge
                size="sm"
                class="cursor-pointer {{ $badge['hasOverride'] ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300' : '' }}"
                x-on:click="$flux.modal('{{ $modalName }}').show(); $nextTick(() => $dispatch('focus-field', { field: '{{ $badge['field'] }}' }))"
            >
                {{ $badge['label'] }}
            </flux:badge>
        @endforeach
    </div>

    <flux:modal :name="$modalName" flyout class="w-96" x-on:focus-field.window="$nextTick(() => {
        const input = $el.querySelector(`[data-field='${$event.detail.field}']`);
        if (input) input.focus();
    })">
        <div class="space-y-6" x-data="{
            target: {{ $config['target'] }},
            startingReps: {{ $config['startingReps'] }},
            sets: {{ $config['sets'] }},
            save() {
                $wire.updateExerciseOverride({{ $exerciseId }}, 'target', parseFloat(this.target));
                $wire.updateExerciseOverride({{ $exerciseId }}, 'startingReps', parseInt(this.startingReps));
                $wire.updateExerciseOverride({{ $exerciseId }}, 'sets', parseInt(this.sets));
                $flux.modal('{{ $modalName }}').close();
            }
        }">
            <flux:heading size="lg">{{ $exercise->name }}</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Target</flux:label>
                    <flux:input.group>
                        <flux:input
                            x-model="target"
                            type="number"
                            min="0"
                            step="0.5"
                            data-field="target"
                        />
                        <flux:input.group.suffix>%</flux:input.group.suffix>
                    </flux:input.group>
                </flux:field>

                <flux:field>
                    <flux:label>Starting Reps</flux:label>
                    <flux:input.group>
                        <flux:input
                            x-model="startingReps"
                            type="number"
                            min="1"
                            max="25"
                            step="1"
                            data-field="startingReps"
                        />
                        <flux:input.group.suffix>reps</flux:input.group.suffix>
                    </flux:input.group>
                </flux:field>

                <flux:field>
                    <flux:label>Sets</flux:label>
                    <flux:input.group>
                        <flux:input
                            x-model="sets"
                            type="number"
                            min="1"
                            max="6"
                            step="1"
                            data-field="sets"
                        />
                        <flux:input.group.suffix>sets</flux:input.group.suffix>
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
            <table class="border-collapse border border-zinc-300 dark:border-zinc-600">
                <thead>
                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2">Week</th>
                        <th class="border border-zinc-300 dark:border-zinc-600 px-2 py-2"># Sessions</th>
                        @for ($i = 0; $i < $setCount; $i++)
                            <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2">Set {{ $i + 1 }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weeks as $weekIndex => $week)
                        @php
                            $sessionCount = count($week->sessions);
                            $merged = $mergeIdenticalSessions && $sessionsAreIdentical($week->sessions);
                        @endphp
                        @if ($merged)
                            @php $session = $week->sessions[0]; @endphp
                            <tr>
                                <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle"
                                    rowspan="3">
                                    W{{ $weekIndex + 1 }}
                                </td>
                                <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center bg-zinc-100 dark:bg-zinc-700/50 text-xs font-medium"
                                    rowspan="3">
                                    {{ $sessionCount }}
                                </td>
                                @for ($i = 0; $i < $setCount; $i++)
                                    @if (isset($session->sets[$i]))
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $cellClasses['reps'] }}">
                                            {{ $session->sets[$i]->reps ?? '-' }}
                                        </td>
                                    @else
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center" rowspan="3"></td>
                                    @endif
                                @endfor
                            </tr>
                            <tr>
                                @for ($i = 0; $i < $setCount; $i++)
                                    @if (isset($session->sets[$i]))
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $cellClasses['weight'] }}">
                                            {{ $session->sets[$i]->weight !== null ? number_format($session->sets[$i]->weight, 1) : '-' }}
                                        </td>
                                    @endif
                                @endfor
                            </tr>
                            <tr>
                                @for ($i = 0; $i < $setCount; $i++)
                                    @if (isset($session->sets[$i]))
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-1 text-center text-xs text-zinc-500 dark:text-zinc-400 {{ $cellClasses['oneRepMax'] }}">
                                            {{ $session->sets[$i]->oneRepMax !== null ? number_format($session->sets[$i]->oneRepMax, 1) : '-' }}
                                        </td>
                                    @endif
                                @endfor
                            </tr>
                        @else
                            @foreach ($week->sessions as $sessionIndex => $session)
                                @php $isFirstSession = $sessionIndex === 0; @endphp
                                <tr>
                                    @if ($isFirstSession)
                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-bold bg-zinc-50 dark:bg-zinc-800/50 align-middle"
                                            rowspan="{{ $sessionCount * 3 }}">
                                            W{{ $weekIndex + 1 }}
                                        </td>
                                    @endif
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center bg-zinc-100 dark:bg-zinc-700/50 text-xs font-medium"
                                        rowspan="3">
                                        S{{ $sessionIndex + 1 }}
                                    </td>
                                    @for ($i = 0; $i < $setCount; $i++)
                                        @if (isset($session->sets[$i]))
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $cellClasses['reps'] }}">
                                                {{ $session->sets[$i]->reps ?? '-' }}
                                            </td>
                                        @else
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center" rowspan="3"></td>
                                        @endif
                                    @endfor
                                </tr>
                                <tr>
                                    @for ($i = 0; $i < $setCount; $i++)
                                        @if (isset($session->sets[$i]))
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $cellClasses['weight'] }}">
                                                {{ $session->sets[$i]->weight !== null ? number_format($session->sets[$i]->weight, 1) : '-' }}
                                            </td>
                                        @endif
                                    @endfor
                                </tr>
                                <tr>
                                    @for ($i = 0; $i < $setCount; $i++)
                                        @if (isset($session->sets[$i]))
                                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-1 text-center text-xs text-zinc-500 dark:text-zinc-400 {{ $cellClasses['oneRepMax'] }}">
                                                {{ $session->sets[$i]->oneRepMax !== null ? number_format($session->sets[$i]->oneRepMax, 1) : '-' }}
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
