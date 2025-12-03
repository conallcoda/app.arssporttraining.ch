<?php

use App\Data\Form\FluxFieldset;
use App\Models\Training\Progression\Config\ProgressionConfig;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $showProgressionModal = false;

    public ?string $weightStrategy = null;

    public ?float $targetImprovement = null;

    public ?float $incrementStep = null;

    public ?string $repStrategy = null;

    public ?int $startingReps = null;

    public ?int $stepDownInterval = null;

    public ?int $repDecrement = null;

    public ?int $minimumReps = null;

    public ?int $reps = null;

    public function getFieldsProperty(): array
    {
        return ProgressionConfig::getFields([
            'weightStrategy' => $this->weightStrategy,
            'repStrategy' => $this->repStrategy,
        ]);
    }

    #[\Livewire\Attributes\On('open-progression-modal')]
    public function openProgressionModal(?array $progressionConfig = null): void
    {
        $this->weightStrategy = $progressionConfig['weightConfig']['type'] ?? 'fixed_step';
        $this->targetImprovement = $progressionConfig['weightConfig']['targetImprovement'] ?? 12.5;
        $this->incrementStep = $progressionConfig['weightConfig']['incrementStep'] ?? 0.5;

        $this->repStrategy = $progressionConfig['repConfig']['type'] ?? 'paired_ladder';
        $this->startingReps = $progressionConfig['repConfig']['startingReps'] ?? 12;
        $this->stepDownInterval = $progressionConfig['repConfig']['stepDownInterval'] ?? 2;
        $this->repDecrement = $progressionConfig['repConfig']['repDecrement'] ?? 2;
        $this->minimumReps = $progressionConfig['repConfig']['minimumReps'] ?? 6;
        $this->reps = $progressionConfig['repConfig']['reps'] ?? 8;

        $this->showProgressionModal = true;
    }

    public function close(): void
    {
        $this->reset([
            'showProgressionModal',
            'weightStrategy',
            'targetImprovement',
            'incrementStep',
            'repStrategy',
            'startingReps',
            'stepDownInterval',
            'repDecrement',
            'minimumReps',
            'reps',
        ]);
    }

    public function save(): void
    {
        $rules = FluxFieldset::buildValidationRules($this->fields);

        $this->validate($rules);

        $this->dispatch('progression-config-saved', [
            'weightStrategy' => $this->weightStrategy,
            'targetImprovement' => (float) $this->targetImprovement,
            'incrementStep' => (float) $this->incrementStep,
            'repStrategy' => $this->repStrategy,
            'startingReps' => (int) $this->startingReps,
            'stepDownInterval' => (int) $this->stepDownInterval,
            'repDecrement' => (int) $this->repDecrement,
            'minimumReps' => (int) $this->minimumReps,
            'reps' => (int) $this->reps,
        ]);

        $this->close();
    }
} ?>

<div>
    <flux:modal name="progression-modal" wire:model="showProgressionModal" variant="flyout" class="w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">Progression Settings</flux:heading>
                <flux:subheading>Configure weight and rep progression strategies</flux:subheading>
            </div>

            <x-flux-form :fields="$this->fields" />

            <div class="flex gap-2 justify-end pt-4">
                <flux:button type="button" variant="ghost" wire:click="close">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
