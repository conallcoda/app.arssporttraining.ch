<?php

namespace Database\Seeders;

use App\Models\Exercise\Exercise;
use App\Models\ProgramCategory;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Database\Seeder;

class TrainingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ProgramCategory::pluck('id', 'name');

        $plan = TrainingPlan::create([
            'name' => 'Default Training Plan',
        ]);

        $plan->userGroups()->attach(UserGroup::first(), ['sort' => 0]);

        $users = User::latest('id')->take(2)->pluck('id');
        $usersWithSort = $users->mapWithKeys(fn ($id, $index) => [$id => ['sort' => $index]])->all();
        $plan->users()->attach($usersWithSort);

        $programs = [];

        $programs[] = $this->createStrengthProgram($plan, $categories->get('Strength'));
        $programs[] = $this->createConditioningProgram($plan, $categories->get('Endurance'));
        $programs[] = $this->createCardioProgram($plan, $categories->get('Coordination'));

        $this->initializeScheduleWithPrograms($plan, $programs);
    }

    private function createStrengthProgram(TrainingPlan $plan, ?int $categoryId): TrainingPlanProgram
    {
        $program = TrainingPlanProgram::create([
            'training_plan_id' => $plan->id,
            'name' => 'Strength',
            'program_category_id' => $categoryId,
        ]);

        $exercises = Exercise::whereJsonContains('config->settings', 'weight')->take(3)->get();

        $attachData = [];
        foreach ($exercises as $index => $exercise) {
            $configArray = json_decode($exercise->getRawOriginal('config') ?? '{}', true) ?: [];
            $attachData[$exercise->id] = [
                'sort' => $index,
                'config' => [
                    'oneRepMaxModifier' => $configArray['weight']['oneRepMaxModifier'] ?? 100,
                    'startingReps' => $configArray['reps']['default'] ?? 12,
                    'tempo' => $configArray['tempo']['default'] ?? '3010',
                    'rest' => $configArray['rest']['default'] ?? 30,
                ],
            ];
        }
        $program->exercises()->attach($attachData);

        return $program;
    }

    private function createConditioningProgram(TrainingPlan $plan, ?int $categoryId): TrainingPlanProgram
    {
        $program = TrainingPlanProgram::create([
            'training_plan_id' => $plan->id,
            'name' => 'Conditioning',
            'program_category_id' => $categoryId,
        ]);

        $exercises = Exercise::whereJsonContains('config->settings', 'duration')->take(2)->get();

        $attachData = [];
        foreach ($exercises as $index => $exercise) {
            $configArray = json_decode($exercise->getRawOriginal('config') ?? '{}', true) ?: [];
            $attachData[$exercise->id] = [
                'sort' => $index,
                'config' => [
                    'sets' => $configArray['sets']['default'] ?? 3,
                    'rest' => $configArray['rest']['default'] ?? 30,
                ],
            ];
        }
        $program->exercises()->attach($attachData);

        return $program;
    }

    private function createCardioProgram(TrainingPlan $plan, ?int $categoryId): TrainingPlanProgram
    {
        $program = TrainingPlanProgram::create([
            'training_plan_id' => $plan->id,
            'name' => 'Cardio',
            'program_category_id' => $categoryId,
        ]);

        $exercises = Exercise::whereJsonContains('config->settings', 'pace')->take(2)->get();

        $attachData = [];
        foreach ($exercises as $index => $exercise) {
            $configArray = json_decode($exercise->getRawOriginal('config') ?? '{}', true) ?: [];
            $attachData[$exercise->id] = [
                'sort' => $index,
                'config' => [
                    'pace' => $configArray['pace']['default'] ?? '00:00',
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
