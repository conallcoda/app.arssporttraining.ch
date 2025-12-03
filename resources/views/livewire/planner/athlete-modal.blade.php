<?php

use App\Models\Training\Progression\Athlete\AthleteData;
use App\Models\Training\Progression\Reference\RepPercentageTable;
use function Livewire\Volt\{state, computed, on, updated};

state([
    'showAthleteModal' => false,
    'athleteId' => null,
    'tests' => [],
]);

$fields = computed(fn() => AthleteData::getFields());

on([
    'open-athlete-modal' => function (array $athleteData = null) {
        $this->athleteId = $athleteData['athleteId'] ?? null;
        $this->tests = [];

        if (!empty($athleteData['tests'])) {
            foreach ($athleteData['tests'] as $exerciseId => $test) {
                $reps = $test['reps'] ?? null;
                $weight = $test['weight'] ?? null;
                $value = null;

                if ($reps && $weight) {
                    $percentage = RepPercentageTable::getPercentage((int) $reps);
                    $value = round($weight / $percentage, 1);
                }

                $this->tests[] = [
                    'exerciseId' => $test['exerciseId'] ?? $exerciseId,
                    'reps' => $reps,
                    'weight' => $weight,
                    'value' => $value,
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

$addRepeaterItem = function (string $name) {
    $this->{$name}[] = [
        'exerciseId' => null,
        'reps' => null,
        'weight' => null,
        'value' => null,
    ];
};

$recalculateValue = function (int $index) {
    $test = $this->tests[$index] ?? null;
    if ($test && $test['reps'] && $test['weight']) {
        $percentage = RepPercentageTable::getPercentage((int) $test['reps']);
        $this->tests[$index]['value'] = round($test['weight'] / $percentage, 1);
    } else {
        $this->tests[$index]['value'] = null;
    }
};

updated([
    'tests.*.reps' => function ($value, $key) {
        preg_match('/tests\.(\d+)\.reps/', $key, $matches);
        if (isset($matches[1])) {
            $this->recalculateValue((int) $matches[1]);
        }
    },
    'tests.*.weight' => function ($value, $key) {
        preg_match('/tests\.(\d+)\.weight/', $key, $matches);
        if (isset($matches[1])) {
            $this->recalculateValue((int) $matches[1]);
        }
    },
]);

$removeRepeaterItem = function (string $name, int $index) {
    unset($this->{$name}[$index]);
    $this->{$name} = array_values($this->{$name});
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

            <x-flux-form :fields="$this->fields" />

            <div class="flex gap-2 justify-end pt-4">
                <flux:button type="button" variant="ghost" wire:click="close">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
