<?php

namespace App\Livewire\Training\View;

use App\Data\AbstractData;
use App\Data\Form\FluxField;
use App\Models\Contracts\HasForms;
use App\Models\Exercise\Exercise;
use App\Models\TrainingPlanProgram;

class TrainingProgramData extends AbstractData implements HasForms
{
    public function __construct(
        public ?int $id,
        public ?int $training_plan_id,
        public string $name,
        public array $exercises = [],
    ) {}

    public static function fromTrainingPlanProgram(TrainingPlanProgram $program): self
    {
        $exercises = [];
        if ($program->relationLoaded('exercises')) {
            $exercises = $program->exercises->map(fn ($exercise) => [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'sort' => $exercise->pivot->sort ?? 0,
            ])->all();
        }

        return new self(
            id: $program->id,
            training_plan_id: $program->training_plan_id,
            name: $program->name ?? '',
            exercises: $exercises,
        );
    }

    public function persist(): void
    {
        if ($this->id === null) {
            $program = TrainingPlanProgram::create([
                'training_plan_id' => $this->training_plan_id,
                'name' => $this->name,
            ]);
            $this->id = $program->id;
        } else {
            $program = TrainingPlanProgram::findOrFail($this->id);
            $program->name = $this->name;
            $program->save();
        }

        $syncData = collect($this->exercises)
            ->filter(fn ($exercise) => ! empty($exercise['id']))
            ->mapWithKeys(fn ($exercise, $index) => [
                $exercise['id'] => ['sort' => $exercise['sort'] ?? $index],
            ])
            ->all();

        $program->exercises()->sync($syncData);
    }

    public static function getFields(): array
    {
        $exerciseOptions = Exercise::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($exercise) => [$exercise->id => $exercise->name])
            ->all();

        return [
            FluxField::text('name')
                ->label('Name')
                ->placeholder('Program name')
                ->required()
                ->default('')
                ->rules('required|string|min:1'),
            FluxField::relationship('exercises')
                ->label('Exercises')
                ->options($exerciseOptions)
                ->placeholder('Select exercise')
                ->sortable()
                ->default([]),
        ];
    }
}
