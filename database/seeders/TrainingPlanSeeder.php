<?php

namespace Database\Seeders;

use App\Data\Exercise\Types\StrengthExerciseConfig;
use App\Livewire\Training\View\AthleteTrainingProgramData;
use App\Models\Exercise\Exercise;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Support\WeekOptions;
use Illuminate\Database\Seeder;

class TrainingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plan = TrainingPlan::create([
            'name' => 'Default Training Plan',
        ]);

        $plan->config->set('users.default.training_plan', [
            'start_date' => WeekOptions::getCurrentWeekValue(),
            'duration' => AthleteTrainingProgramData::DEFAULT_DURATION,
            'measured_reps' => AthleteTrainingProgramData::DEFAULT_MEASURED_REPS,
            'measured_weight' => AthleteTrainingProgramData::DEFAULT_MEASURED_WEIGHT,
            'target_goal' => AthleteTrainingProgramData::DEFAULT_TARGET_GOAL,
        ]);
        $plan->save();

        $plan->userGroups()->attach(UserGroup::first(), ['sort' => 0]);

        $users = User::latest('id')->take(2)->pluck('id');
        $usersWithSort = $users->mapWithKeys(fn($id, $index) => [$id => ['sort' => $index]])->all();
        $plan->users()->attach($usersWithSort);

        $exercises = Exercise::take(4)->get();

        if ($exercises->count() >= 4) {
            $program1 = TrainingPlanProgram::create([
                'training_plan_id' => $plan->id,
                'name' => 'Strength 1',
            ]);
            $program1->exercises()->attach([
                $exercises[0]->id => ['sort' => 0, 'config' => $this->getExerciseConfig($exercises[0])],
                $exercises[1]->id => ['sort' => 1, 'config' => $this->getExerciseConfig($exercises[1])],
            ]);

            $program2 = TrainingPlanProgram::create([
                'training_plan_id' => $plan->id,
                'name' => 'Strength 2',
            ]);
            $program2->exercises()->attach([
                $exercises[2]->id => ['sort' => 0, 'config' => $this->getExerciseConfig($exercises[2])],
                $exercises[3]->id => ['sort' => 1, 'config' => $this->getExerciseConfig($exercises[3])],
            ]);
        }
    }

    private function getExerciseConfig(Exercise $exercise): array
    {
        $defaults = StrengthExerciseConfig::fromExercise($exercise);

        return [
            'oneRepMaxModifier' => $defaults->oneRepMaxModifier,
            'startingReps' => $defaults->startingReps,
            'timeUnderTension' => $defaults->timeUnderTension,
            'rest' => $defaults->rest,
        ];
    }
}
