<?php

use App\Models\Exercise\Exercise;
use App\Models\Users\Types\Athlete;
use function Livewire\Volt\{state, computed, on};

state([
    'showAthleteModal' => false,
    'athleteId' => null,
    'tests' => [],
]);

$athletes = computed(fn() => Athlete::orderBy('forename')->orderBy('surname')->get());

$exerciseOptions = computed(fn() => Exercise::orderBy('name')->get());

on([
    'open-athlete-modal' => function (array $athleteData = null) {
        $this->athleteId = $athleteData['athleteId'] ?? null;
        $this->tests = [];

        if (!empty($athleteData['tests'])) {
            foreach ($athleteData['tests'] as $exerciseId => $test) {
                $this->tests[] = [
                    'exerciseId' => $test['exerciseId'] ?? $exerciseId,
                    'reps' => $test['reps'] ?? null,
                    'weight' => $test['weight'] ?? null,
                ];
            }
        }

        $this->showAthleteModal = true;
    },
]);

$close = function () {
    $this->reset([
        'showAthleteModal',
        'athleteId',
        'tests',
    ]);
};

$addTest = function () {
    $this->tests[] = [
        'exerciseId' => null,
        'reps' => null,
        'weight' => null,
    ];
};

$removeTest = function (int $index) {
    unset($this->tests[$index]);
    $this->tests = array_values($this->tests);
};

$save = function () {
    $this->validate([
        'tests.*.exerciseId' => 'nullable|integer|exists:exercises,id',
        'tests.*.reps' => 'nullable|integer|min:1|max:30',
        'tests.*.weight' => 'nullable|numeric|min:0',
    ]);

    $formattedTests = [];
    foreach ($this->tests as $test) {
        if ($test['exerciseId'] && $test['reps'] && $test['weight']) {
            $formattedTests[$test['exerciseId']] = [
                'exerciseId' => (int) $test['exerciseId'],
                'reps' => (int) $test['reps'],
                'weight' => (float) $test['weight'],
            ];
        }
    }

    $this->dispatch('athlete-data-saved', [
        'athleteId' => $this->athleteId ? (int) $this->athleteId : null,
        'tests' => $formattedTests,
    ]);

    $this->close();
};

?>

<div>
    <flux:modal name="athlete-modal" wire:model="showAthleteModal" variant="flyout" class="w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">Configure Athlete</flux:heading>
                <flux:subheading>Set up athlete and test results</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Athlete</flux:label>
                <flux:select wire:model="athleteId" placeholder="Select an athlete">
                    <flux:select.option value="">No athlete selected</flux:select.option>
                    @foreach ($this->athletes as $athlete)
                        <flux:select.option value="{{ $athlete->id }}">{{ $athlete->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:label>Test Results</flux:label>
                        <flux:button type="button" size="sm" variant="ghost" wire:click="addTest" icon="plus">
                            Add
                        </flux:button>
                    </div>

                    @if (is_array($tests) && count($tests) > 0)
                        <div class="space-y-3">
                            @foreach ($tests as $index => $test)
                                @php
                                    $selectedIds = collect($tests)
                                        ->filter(fn($t, $i) => $t['exerciseId'] !== null && $i !== $index)
                                        ->pluck('exerciseId')
                                        ->map(fn($id) => (int) $id)
                                        ->toArray();
                                @endphp
                                <div class="flex items-start gap-2" wire:key="test-{{ $index }}">
                                    <div class="flex-1 space-y-2">
                                        <flux:select
                                            wire:model="tests.{{ $index }}.exerciseId"
                                            placeholder="Select an exercise"
                                            size="sm"
                                        >
                                            <flux:select.option value="">Select an exercise</flux:select.option>
                                            @foreach ($this->exerciseOptions as $exercise)
                                                @if (!in_array($exercise->id, $selectedIds))
                                                    <flux:select.option value="{{ $exercise->id }}">
                                                        {{ $exercise->name }}
                                                    </flux:select.option>
                                                @endif
                                            @endforeach
                                        </flux:select>
                                        <div class="flex gap-2">
                                            <flux:input
                                                wire:model="tests.{{ $index }}.reps"
                                                type="number"
                                                placeholder="Reps"
                                                size="sm"
                                                min="1"
                                                max="30"
                                            />
                                            <flux:input
                                                wire:model="tests.{{ $index }}.weight"
                                                type="number"
                                                placeholder="Weight (kg)"
                                                size="sm"
                                                step="0.5"
                                                min="0"
                                            />
                                        </div>
                                    </div>
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="removeTest({{ $index }})"
                                        class="mt-1"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-zinc-500">No test results added yet.</p>
                    @endif
                </div>
            </flux:field>

            <div class="flex gap-2 justify-end pt-4">
                <flux:button type="button" variant="ghost" wire:click="close">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
