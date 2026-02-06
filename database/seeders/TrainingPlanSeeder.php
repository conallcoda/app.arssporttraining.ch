<?php

namespace Database\Seeders;

use App\Data\Training\DefaultTrainingProgramData;
use App\Models\Exercise\Exercise;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

            $this->initializeScheduleWithPrograms($plan, $program1, $program2);
        }
    }

    private function initializeScheduleWithPrograms(TrainingPlan $plan, TrainingPlanProgram $program1, TrainingPlanProgram $program2): void
    {
        $week1Id = (string) Str::uuid();

        $slots = [
            ['day' => 0, 'slot' => 0, 'programId' => $program1->id],
            ['day' => 4, 'slot' => 0, 'programId' => $program1->id, 'isLinked' => true],
            ['day' => 1, 'slot' => 1, 'programId' => $program2->id],
        ];

        $weeks = [
            [
                'id' => $week1Id,
                'linkedToWeekId' => null,
                'slots' => $slots,
            ],
        ];

        for ($i = 2; $i <= 5; $i++) {
            $weeks[] = [
                'id' => (string) Str::uuid(),
                'linkedToWeekId' => $week1Id,
                'slots' => [],
            ];
        }

        $plan->config->set('schedule.weeks', $weeks);
        $plan->save();
    }

    private function getExerciseConfig(Exercise $exercise): array
    {
        $defaults = $exercise->config?->strength;

        return [
            'oneRepMaxModifier' => $defaults?->oneRepMaxModifier ?? 100,
            'startingReps' => $defaults?->startingReps ?? 12,
            'timeUnderTension' => $defaults?->timeUnderTension ?? '3010',
            'rest' => $defaults?->rest ?? 30,
        ];
    }
}
