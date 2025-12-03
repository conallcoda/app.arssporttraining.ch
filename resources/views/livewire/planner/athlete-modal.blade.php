<?php

use App\Data\Form\FluxFieldset;
use App\Models\Training\Progression\Athlete\AthleteData;
use App\Models\Training\Progression\Reference\RepPercentageTable;
use Livewire\Volt\Component;

new class extends Component {
    public bool $showAthleteModal = false;
    public ?int $athleteId = null;
    public array $tests = [];

    public function getFieldsProperty(): array
    {
        return AthleteData::getFields();
    }

    #[\Livewire\Attributes\On('open-athlete-modal')]
    public function openAthleteModal(?array $athleteData = null): void
    {
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
    }

    public function close(): void
    {
        $this->reset([
            'showAthleteModal',
            'athleteId',
            'tests',
        ]);
    }

    public function addRepeaterItem(string $name): void
    {
        $this->{$name}[] = [
            'exerciseId' => null,
            'reps' => null,
            'weight' => null,
            'value' => null,
        ];
    }

    public function recalculateValue(int $index): void
    {
        $test = $this->tests[$index] ?? null;
        if ($test && $test['reps'] && $test['weight']) {
            $percentage = RepPercentageTable::getPercentage((int) $test['reps']);
            $this->tests[$index]['value'] = round($test['weight'] / $percentage, 1);
        } else {
            $this->tests[$index]['value'] = null;
        }
    }

    public function updated($property): void
    {
        if (preg_match('/tests\.(\d+)\.(reps|weight)/', $property, $matches)) {
            $this->recalculateValue((int) $matches[1]);
        }
    }

    public function removeRepeaterItem(string $name, int $index): void
    {
        unset($this->{$name}[$index]);
        $this->{$name} = array_values($this->{$name});
    }

    public function save(): void
    {
        $rules = FluxFieldset::buildValidationRules($this->fields);
        $rules['tests.*.exerciseId'] .= '|distinct';

        $this->validate($rules, [
            'tests.*.exerciseId.distinct' => 'Each exercise can only be selected once.',
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
    }
} ?>

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
