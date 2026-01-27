<?php

namespace Database\Seeders;

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseType\StrengthDefaults;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Database\Seeder;

class TrainingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plan = TrainingPlan::create([
            'name' => 'Default Training Plan',
            'start_date' => now()->startOfWeek(),
            'duration' => 5,
        ]);

        $plan->userGroups()->attach(UserGroup::first());
        $plan->users()->attach(User::latest('id')->take(2)->pluck('id'));

        $exercises = Exercise::take(4)->get();

        if ($exercises->count() >= 4) {
            $program1 = TrainingPlanProgram::create([
                'training_plan_id' => $plan->id,
                'name' => 'Strength 1',
            ]);
            $program1->exercises()->attach([
                $exercises[0]->id => ['sort' => 0, 'extra' => $this->getExerciseExtra($exercises[0])],
                $exercises[1]->id => ['sort' => 1, 'extra' => $this->getExerciseExtra($exercises[1])],
            ]);

            $program2 = TrainingPlanProgram::create([
                'training_plan_id' => $plan->id,
                'name' => 'Strength 2',
            ]);
            $program2->exercises()->attach([
                $exercises[2]->id => ['sort' => 0, 'extra' => $this->getExerciseExtra($exercises[2])],
                $exercises[3]->id => ['sort' => 1, 'extra' => $this->getExerciseExtra($exercises[3])],
            ]);
        }
    }

    private function getExerciseExtra(Exercise $exercise): array
    {
        $defaults = StrengthDefaults::fromExercise($exercise);

        return [
            'oneRepMaxModifier' => $defaults->oneRepMaxModifier,
            'startingReps' => $defaults->startingReps,
            'timeUnderTension' => $defaults->timeUnderTension,
            'rest' => $defaults->rest,
        ];
    }
}
