<?php

namespace Database\Seeders;

use App\Data\Training\DefaultTrainingProgramData;
use App\Models\Exercise\Exercise;
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
        ]);

        $defaultData = new DefaultTrainingProgramData;
        $defaultData->persist($plan);

        $plan->userGroups()->attach(UserGroup::first(), ['sort' => 0]);

        $users = User::latest('id')->take(2)->pluck('id');
        $usersWithSort = $users->mapWithKeys(fn ($id, $index) => [$id => ['sort' => $index]])->all();
        $plan->users()->attach($usersWithSort);

        $programs = [];

        $programs[] = $this->createStrengthAutomaticProgram($plan);
        $programs[] = $this->createStrengthManualProgram($plan);
        $programs[] = $this->createConditioningProgram($plan);
        $programs[] = $this->createTimedProgram($plan);
        $programs[] = $this->createCardioProgram($plan);

        $this->initializeScheduleWithPrograms($plan, $programs);
    }

    private function createStrengthAutomaticProgram(TrainingPlan $plan): TrainingPlanProgram
    {
        $program = TrainingPlanProgram::create([
            'training_plan_id' => $plan->id,
            'name' => 'Strength Auto',
        ]);

        $exercises = Exercise::where('type', 'strength_automatic')->take(2)->get();

        $attachData = [];
        foreach ($exercises as $index => $exercise) {
            $config = $exercise->config?->strength_automatic;
            $attachData[$exercise->id] = [
                'sort' => $index,
                'config' => [
                    'oneRepMaxModifier' => $config?->oneRepMaxModifier ?? 100,
                    'startingReps' => $config?->startingReps ?? 12,
                    'tempo' => $config?->tempo ?? '3010',
                    'rest' => $config?->rest ?? 30,
                ],
            ];
        }
        $program->exercises()->attach($attachData);

        return $program;
    }

    private function createStrengthManualProgram(TrainingPlan $plan): TrainingPlanProgram
    {
        $program = TrainingPlanProgram::create([
            'training_plan_id' => $plan->id,
            'name' => 'Strength Manual',
        ]);

        $exercises = Exercise::where('type', 'strength_manual')->take(2)->get();

        $attachData = [];
        foreach ($exercises as $index => $exercise) {
            $config = $exercise->config?->strength_manual;
            $attachData[$exercise->id] = [
                'sort' => $index,
                'config' => [
                    'startingWeight' => $config?->startingWeight ?? 0,
                    'startingReps' => $config?->startingReps ?? 12,
                    'tempo' => $config?->tempo ?? '3010',
                    'rest' => $config?->rest ?? 30,
                ],
            ];
        }
        $program->exercises()->attach($attachData);

        return $program;
    }

    private function createConditioningProgram(TrainingPlan $plan): TrainingPlanProgram
    {
        $program = TrainingPlanProgram::create([
            'training_plan_id' => $plan->id,
            'name' => 'Conditioning',
        ]);

        $exercises = Exercise::where('type', 'conditioning')->take(2)->get();

        $attachData = [];
        foreach ($exercises as $index => $exercise) {
            $config = $exercise->config?->conditioning;
            $attachData[$exercise->id] = [
                'sort' => $index,
                'config' => [
                    'sets' => 3,
                    'rest' => $config?->rest ?? 30,
                ],
            ];
        }
        $program->exercises()->attach($attachData);

        return $program;
    }

    private function createTimedProgram(TrainingPlan $plan): TrainingPlanProgram
    {
        $program = TrainingPlanProgram::create([
            'training_plan_id' => $plan->id,
            'name' => 'Timed',
        ]);

        $exercises = Exercise::where('type', 'timed')->take(2)->get();

        $attachData = [];
        foreach ($exercises as $index => $exercise) {
            $config = $exercise->config?->timed;
            $attachData[$exercise->id] = [
                'sort' => $index,
                'config' => [
                    'duration' => $config?->duration ?? 60,
                    'sets' => 3,
                    'rest' => $config?->rest ?? 30,
                ],
            ];
        }
        $program->exercises()->attach($attachData);

        return $program;
    }

    private function createCardioProgram(TrainingPlan $plan): TrainingPlanProgram
    {
        $program = TrainingPlanProgram::create([
            'training_plan_id' => $plan->id,
            'name' => 'Cardio',
        ]);

        $exercises = Exercise::where('type', 'cardio')->take(2)->get();

        $attachData = [];
        foreach ($exercises as $index => $exercise) {
            $config = $exercise->config?->cardio;
            $attachData[$exercise->id] = [
                'sort' => $index,
                'config' => [
                    'pace' => $config?->pace ?? '00:00',
                    'watts' => $config?->watts ?? 0,
                ],
            ];
        }
        $program->exercises()->attach($attachData);

        return $program;
    }

    private function initializeScheduleWithPrograms(TrainingPlan $plan, array $programs): void
    {
        $week1Id = 'default_0';

        $slots = [];
        foreach ($programs as $index => $program) {
            $slots[] = ['day' => $index, 'slot' => 0, 'programId' => $program->id];
        }

        $weeks = [
            [
                'id' => $week1Id,
                'linkedTo' => null,
                'slots' => $slots,
                'sort' => 0,
            ],
        ];

        for ($i = 1; $i < 5; $i++) {
            $weeks[] = [
                'id' => "default_{$i}",
                'linkedTo' => $week1Id,
                'slots' => [],
                'sort' => $i,
            ];
        }

        $plan->config->set('default.schedule.weeks', $weeks);
        $plan->save();
    }
}
