<?php

namespace App\Livewire\Training\View;

use App\Data\Form\TableColumn;
use App\Models\TrainingPlanProgram;
use App\Models\TrainingPlanProgramExercise;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ProgramExerciseOverrideList extends Component
{
    public TrainingPlanProgram $program;

    public int $refreshKey = 0;

    #[Computed]
    public function items(): array
    {
        return TrainingPlanProgramExercise::query()
            ->where('training_plan_program_id', $this->program->id)
            ->with('exercise')
            ->orderBy('sort')
            ->get()
            ->map(fn ($pivot) => ProgramExerciseOverrideData::fromPivot($pivot))
            ->all();
    }

    #[Computed]
    public function columns(): array
    {
        return [
            TableColumn::text('name')->label('Exercise')->width('w-40'),
            TableColumn::percentage('oneRepMaxModifier')
                ->label('1RM')
                ->inline()
                ->width('w-20'),
            TableColumn::number('startingReps')
                ->label('Reps')
                ->inline()
                ->suffix(' reps')
                ->min(1)
                ->step(1)
                ->width('w-28'),
            TableColumn::tut('timeUnderTension')
                ->label('TUT')
                ->inline()
                ->width('w-32'),
            TableColumn::number('rest')
                ->label('Rest')
                ->inline()
                ->suffix(' s')
                ->min(0)
                ->step(5)
                ->width('w-24'),
        ];
    }

    public function update(int $id, string $field, mixed $value): void
    {
        $column = collect($this->columns)->firstWhere('field', $field);

        if ($column && ! $column->isValid($value)) {
            return;
        }

        $pivot = TrainingPlanProgramExercise::with('exercise')->findOrFail($id);
        $defaults = ProgramExerciseOverrideData::getDefaults($pivot->exercise);
        $defaultValue = $defaults[$field] ?? null;

        $shouldClear = $value === '' || $value === null || (string) $value === (string) $defaultValue;

        if ($shouldClear) {
            $extra = $pivot->extra->all();
            unset($extra[$field]);
            $pivot->extra = $extra;
        } else {
            $pivot->extra->set($field, $value);
        }

        $pivot->save();

        unset($this->items);
        $this->refreshKey++;
    }

    public function getPlaceholder(int $exerciseId, string $field): mixed
    {
        $pivot = TrainingPlanProgramExercise::with('exercise')->find($exerciseId);
        if (! $pivot) {
            return null;
        }

        $defaults = ProgramExerciseOverrideData::getDefaults($pivot->exercise);

        return $defaults[$field] ?? null;
    }

    public function render(): View
    {
        return view('livewire.training.view.program-exercise-override-list');
    }
}
