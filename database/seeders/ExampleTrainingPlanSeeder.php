<?php

namespace Database\Seeders;

use App\Data\Training\Config\TrainingPlanConfig;
use App\Models\Exercise\Exercise;
use App\Models\ProgramCategory;
use App\Models\Tag;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ExampleTrainingPlanSeeder extends Seeder
{
    private Collection $categories;

    private Collection $equipment;

    private Collection $modifiers;

    public function run(): void
    {
        $this->categories = Tag::forScope('exercise_category')->pluck('id', 'name');
        $this->equipment = Tag::forScope('exercise_equipment')->pluck('id', 'name');
        $this->modifiers = Tag::forScope('exercise_modifiers')->pluck('id', 'name');

        $programCategories = ProgramCategory::pluck('id', 'name');

        $plan = TrainingPlan::create(['name' => 'Example']);

        $plan->userGroups()->attach(UserGroup::first(), ['sort' => 0]);

        $users = User::latest('id')->take(2)->pluck('id');
        $usersWithSort = $users->mapWithKeys(fn ($id, $index) => [$id => ['sort' => $index]])->all();
        $plan->users()->attach($usersWithSort);

        $programs = [];
        $programs[] = $this->createStrength1A($plan, $programCategories->get('Strength'));
        $programs[] = $this->createStrength1B($plan, $programCategories->get('Strength'));
        $programs[] = $this->createCore($plan, $programCategories->get('Core'));
        $programs[] = $this->createJumpSchool1($plan, $programCategories->get('Jump'));
        $programs[] = $this->createJumpSchool2($plan, $programCategories->get('Jump'));

        $this->initializeScheduleWithPrograms($plan, $programs);
    }

    private function createStrength1A(TrainingPlan $plan, ?int $categoryId): TrainingPlanProgram
    {
        $program = TrainingPlanProgram::create([
            'training_plan_id' => $plan->id,
            'name' => 'Strength 1A',
            'program_category_id' => $categoryId,
        ]);

        $exercises = [
            [
                'name' => 'Back Squat',
                'category' => 'Squat',
                'equipment' => ['BB'],
                'modifiers' => [],
                'config' => $this->strengthAutoConfig(default: 12, oneRepMaxModifier: 100, tempo: '2010', rest: 120),
            ],
            [
                'name' => 'Wide Deadlift',
                'category' => 'Deadlift',
                'equipment' => ['BB'],
                'modifiers' => ['Wide Stance'],
                'config' => $this->strengthAutoConfig(default: 12, oneRepMaxModifier: 85, tempo: '1010', rest: 120),
            ],
            [
                'name' => 'Reverse Lunge',
                'category' => 'Lunge',
                'equipment' => ['DB'],
                'modifiers' => [],
                'config' => $this->strengthConfig(default: 14, weight: 0, tempo: '1010', rest: 120),
            ],
            [
                'name' => 'Single-Leg Dynamic Bridge',
                'category' => 'Hip Extension',
                'equipment' => [],
                'modifiers' => ['One-Leg', 'Heel Elevated'],
                'config' => $this->strengthConfig(default: 16, weight: 0, tempo: '1010', rest: 120),
            ],
            [
                'name' => 'Bent-Over Row',
                'category' => 'Row',
                'equipment' => ['BB'],
                'modifiers' => ['Bent-Over'],
                'config' => $this->strengthConfig(default: 12, weight: 18, tempo: '2010', rest: 120),
            ],
            [
                'name' => 'Eccentric Pull-Ups',
                'category' => 'Pull-Up',
                'equipment' => [],
                'modifiers' => [],
                'config' => [
                    'settings' => ['duration', 'weight', 'tempo', 'rest'],
                    'duration' => ['unit' => 'seconds', 'default' => 30],
                    'weight' => ['mode' => 'manual', 'default' => 0],
                    'tempo' => ['default' => '2010'],
                    'rest' => ['default' => 120],
                ],
            ],
        ];

        $this->seedExercises($program, $exercises);

        return $program;
    }

    private function createStrength1B(TrainingPlan $plan, ?int $categoryId): TrainingPlanProgram
    {
        $program = TrainingPlanProgram::create([
            'training_plan_id' => $plan->id,
            'name' => 'Strength 1B',
            'program_category_id' => $categoryId,
        ]);

        $exercises = [

            [
                'name' => 'Front Squat',
                'category' => 'Squat',
                'equipment' => ['BB'],
                'modifiers' => ['Front'],
                'config' => $this->strengthAutoConfig(default: 12, oneRepMaxModifier: 85, tempo: '2010', rest: 120),
            ],
            [
                'name' => 'Hamstring Curl',
                'category' => 'Leg Curl',
                'equipment' => ['Leg Curl Machine'],
                'modifiers' => [],
                'config' => $this->strengthConfig(default: 14, weight: 0, tempo: '2010', rest: 120),
            ],
            [
                'name' => 'Step-Up on Bench',
                'category' => 'Step-Up',
                'equipment' => ['DB'],
                'modifiers' => [],
                'config' => $this->strengthConfig(default: 14, weight: 0, tempo: '1010', rest: 120),
            ],
            [
                'name' => 'Flat Bench Press',
                'category' => 'Flat Press',
                'equipment' => ['BB'],
                'modifiers' => ['Flat'],
                'config' => $this->strengthConfig(default: 12, weight: 0, tempo: '3010', rest: 120),
            ],
            [
                'name' => 'Landmine Press',
                'category' => 'Overhead Press',
                'equipment' => ['BB'],
                'modifiers' => ['Kneeling'],
                'config' => $this->strengthConfig(default: 14, weight: 0, tempo: '2010', rest: 120),
            ],
        ];

        $this->seedExercises($program, $exercises);

        return $program;
    }

    private function createCore(TrainingPlan $plan, ?int $categoryId): TrainingPlanProgram
    {
        $program = TrainingPlanProgram::create([
            'training_plan_id' => $plan->id,
            'name' => 'Core',
            'program_category_id' => $categoryId,
        ]);

        $exercises = [
            [
                'name' => 'Static Plank Hold',
                'category' => 'Stabilization',
                'equipment' => [],
                'modifiers' => [],
                'config' => $this->coreConfig(duration: 60, rest: 15),
            ],
            [
                'name' => 'Reverse Plank',
                'category' => 'Stabilization',
                'equipment' => [],
                'modifiers' => ['Supine'],
                'config' => $this->coreConfig(duration: 60, rest: 15),
            ],
            [
                'name' => 'Side Plank',
                'category' => 'Stabilization',
                'equipment' => [],
                'modifiers' => ['Side'],
                'config' => $this->coreConfig(duration: 45, rest: 15),
            ],
            [
                'name' => 'Single-Leg Bridge (Alternating)',
                'category' => 'Hip Extension',
                'equipment' => [],
                'modifiers' => ['One-Leg', 'Alternated'],
                'config' => $this->coreConfig(duration: 60, rest: 15),
            ],
            [
                'name' => 'Shoulder Touch Plank',
                'category' => 'Stabilization',
                'equipment' => [],
                'modifiers' => [],
                'config' => $this->coreConfig(duration: 60, rest: 15),
            ],
            [
                'name' => 'Side Plank with Leg Hold',
                'category' => 'Stabilization',
                'equipment' => [],
                'modifiers' => ['Side', 'One-Leg'],
                'config' => $this->coreConfig(duration: 30, rest: 180),
            ],
        ];

        $this->seedExercises($program, $exercises);

        return $program;
    }

    private function createJumpSchool1(TrainingPlan $plan, ?int $categoryId): TrainingPlanProgram
    {
        $program = TrainingPlanProgram::create([
            'training_plan_id' => $plan->id,
            'name' => 'Jump School 1',
            'program_category_id' => $categoryId,
        ]);

        $exercises = [
            [
                'name' => 'Box Drop Jumps (Two-Legged)',
                'category' => 'Plyometrics',
                'equipment' => [],
                'modifiers' => ['Bilateral'],
                'config' => $this->plyoConfig(reps: 6, sets: 3),
            ],
            [
                'name' => 'Single-Leg Stabilization Jumps',
                'category' => 'Plyometrics',
                'equipment' => [],
                'modifiers' => ['One-Leg'],
                'config' => $this->plyoConfig(reps: 6, sets: 3),
            ],
            [
                'name' => 'Single-Leg Lateral Stabilization Jumps',
                'category' => 'Plyometrics',
                'equipment' => [],
                'modifiers' => ['One-Leg', 'Side'],
                'config' => $this->plyoConfig(reps: 6, sets: 2),
            ],
            [
                'name' => 'Two-Legged Stabilization Jumps Over Low Hurdle',
                'category' => 'Plyometrics',
                'equipment' => [],
                'modifiers' => ['Bilateral'],
                'config' => $this->plyoConfig(reps: 6, sets: 3),
            ],
        ];

        $this->seedExercises($program, $exercises);

        return $program;
    }

    private function createJumpSchool2(TrainingPlan $plan, ?int $categoryId): TrainingPlanProgram
    {
        $program = TrainingPlanProgram::create([
            'training_plan_id' => $plan->id,
            'name' => 'Jump School 2',
            'program_category_id' => $categoryId,
        ]);

        $exercises = [
            [
                'name' => 'Skater Jumps (Stabilize)',
                'category' => 'Plyometrics',
                'equipment' => [],
                'modifiers' => ['Side'],
                'config' => $this->plyoConfig(reps: 5, sets: 3),
            ],
            [
                'name' => 'Dynamic Hops (Straight)',
                'category' => 'Plyometrics',
                'equipment' => [],
                'modifiers' => [],
                'config' => $this->plyoConfig(reps: 8, sets: 3),
            ],
            [
                'name' => 'Dynamic Hops (Lateral)',
                'category' => 'Plyometrics',
                'equipment' => [],
                'modifiers' => ['Side'],
                'config' => $this->plyoConfig(reps: 6, sets: 2),
            ],
            [
                'name' => 'Single-Leg Box Jumps',
                'category' => 'Plyometrics',
                'equipment' => [],
                'modifiers' => ['One-Leg'],
                'config' => $this->plyoConfig(reps: 6, sets: 3),
            ],
            [
                'name' => 'Hurdle Jumps with Intermediate Jump',
                'category' => 'Plyometrics',
                'equipment' => [],
                'modifiers' => [],
                'config' => $this->plyoConfig(reps: 8, sets: 3),
            ],
            [
                'name' => 'Stop and Go (Agility)',
                'category' => 'Plyometrics',
                'equipment' => [],
                'modifiers' => [],
                'config' => $this->plyoConfig(reps: 4, sets: 6),
            ],
        ];

        $this->seedExercises($program, $exercises);

        return $program;
    }

    /** @param array<int, TrainingPlanProgram> $programs */
    private function initializeScheduleWithPrograms(TrainingPlan $plan, array $programs): void
    {
        [$strength1A, $strength1B, $core, $jumpSchool1, $jumpSchool2] = $programs;

        $week1Id = 'default_0';

        $slots = [
            ['day' => 0, 'slot' => 0, 'programs' => [$strength1A->id]],
            ['day' => 0, 'slot' => 1, 'programs' => [$core->id]],
            ['day' => 1, 'slot' => 0, 'programs' => [$jumpSchool1->id]],
            ['day' => 2, 'slot' => 0, 'programs' => [$strength1B->id]],
            ['day' => 3, 'slot' => 0, 'programs' => [$jumpSchool2->id]],
            ['day' => 3, 'slot' => 1, 'programs' => [$core->id]],
            ['day' => 4, 'slot' => 0, 'programs' => [$strength1A->id]],
            ['day' => 4, 'slot' => 1, 'programs' => [$jumpSchool1->id]],
            ['day' => 5, 'slot' => 0, 'programs' => [$strength1B->id]],
            ['day' => 5, 'slot' => 1, 'programs' => [$jumpSchool2->id]],
        ];

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

        $config = TrainingPlanConfig::initialize();
        $config->setDefaultScheduleWeeks($weeks);
        $plan->config = $config;
        $plan->save();
    }

    /** @param array<int, array{name: string, category: string, equipment: list<string>, modifiers: list<string>, config: array<string, mixed>}> $exercises */
    private function seedExercises(TrainingPlanProgram $program, array $exercises): void
    {
        $attachData = [];

        foreach ($exercises as $index => $data) {
            $exercise = Exercise::create([
                'name' => $data['name'],
                'category_id' => $this->categories->get($data['category']),
                'config' => $data['config'],
            ]);

            $tagIds = collect($data['equipment'])
                ->map(fn (string $name) => $this->equipment->get($name))
                ->merge(collect($data['modifiers'])->map(fn (string $name) => $this->modifiers->get($name)))
                ->filter()
                ->all();

            if ($tagIds) {
                $exercise->tags()->sync($tagIds);
            }

            $attachData[$exercise->id] = [
                'sort' => $index,
                'config' => [],
            ];
        }

        $program->exercises()->attach($attachData);
    }

    /** @return array<string, mixed> */
    private function strengthConfig(int $default, float $weight, string $tempo, int $rest): array
    {
        return [
            'settings' => ['reps', 'weight', 'tempo', 'rest'],
            'reps' => ['default' => $default],
            'weight' => ['mode' => 'manual', 'default' => $weight],
            'tempo' => ['default' => $tempo],
            'rest' => ['default' => $rest],
        ];
    }

    /** @return array<string, mixed> */
    private function strengthAutoConfig(int $default, int $oneRepMaxModifier, string $tempo, int $rest): array
    {
        return [
            'settings' => ['reps', 'weight', 'tempo', 'rest'],
            'reps' => ['default' => $default],
            'weight' => ['mode' => 'automatic', 'oneRepMaxModifier' => $oneRepMaxModifier],
            'tempo' => ['default' => $tempo],
            'rest' => ['default' => $rest],
        ];
    }

    /** @return array<string, mixed> */
    private function coreConfig(int $duration, int $rest): array
    {
        return [
            'settings' => ['duration', 'rest'],
            'sets' => ['default' => 5],
            'duration' => ['unit' => 'seconds', 'default' => $duration],
            'rest' => ['default' => $rest],
        ];
    }

    /** @return array<string, mixed> */
    private function plyoConfig(int $reps, int $sets): array
    {
        return [
            'settings' => ['reps'],
            'sets' => ['default' => $sets],
            'reps' => ['mode' => 'manual', 'default' => $reps],
        ];
    }
}
