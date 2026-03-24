<?php

namespace Database\Seeders;

use App\Models\Athlete\MetricSubmission;
use App\Models\Athlete\MetricValue;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseExternal;
use App\Models\Exercise\ExercisePlan;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseTemplate;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Database\Seeder;

class DatabaseImportSeeder extends Seeder
{
    private string $importPath;

    /** @var array<string, int> */
    private array $tagLookup = [];

    /** @var array<string, int> */
    private array $templateLookup = [];

    /** @var array<string, int> */
    private array $exerciseLookup = [];

    /** @var array<string, int> */
    private array $groupLookup = [];

    /** @var array<string, int> */
    private array $userLookup = [];

    /** @var array<string, int> */
    private array $programLookup = [];

    /** @var array<int, int> */
    private array $blockIdMap = [];

    /** @var array<string, int> */
    private array $trainingProgramLookup = [];

    public function run(): void
    {
        $this->importPath = $this->resolveImportPath();

        $this->command->info("Importing from: {$this->importPath}");

        $this->seedTags();
        $this->seedExerciseTemplates();
        $this->seedExercises();
        $this->seedExerciseExternals();
        $this->seedExercisePrograms();
        $this->seedUserGroups();
        $this->seedUsers();
        $this->seedExercisePlans();
        $this->seedTrainingPrograms();
        $this->seedTrainingProgramBlocks();
        $this->seedTrainingProgramSlots();
        $this->seedMetricSubmissions();
    }

    private function seedTags(): void
    {
        $tags = $this->loadFile('tags.php');
        $count = 0;

        foreach ($tags as $tag) {
            $count += $this->seedTagNode($tag, $tag['scope'], null);
        }

        $this->command->info('Imported '.$count.' tags.');
    }

    private function seedTagNode(array $tag, string $scope, ?int $parentId): int
    {
        $model = Tag::updateOrCreate(
            ['scope' => $scope, 'slug' => $tag['slug']],
            [
                'name' => $tag['name'],
                'short_name' => $tag['short_name'] ?? null,
                'color' => $tag['color'] ?? null,
                'parent_id' => $parentId,
                'sort_order' => $tag['sort_order'],
            ],
        );

        $this->tagLookup[$scope.':'.$tag['slug']] = $model->id;

        $count = 1;

        if (! empty($tag['children'])) {
            foreach ($tag['children'] as $child) {
                $count += $this->seedTagNode($child, $scope, $model->id);
            }
        }

        return $count;
    }

    private function seedExerciseTemplates(): void
    {
        $templates = $this->loadFile('exercise_templates.php');

        foreach ($templates as $template) {
            $model = ExerciseTemplate::updateOrCreate(
                ['name' => $template['name']],
                ['config' => $template['config']],
            );

            $this->templateLookup[$template['name']] = $model->id;
        }

        $this->command->info('Imported '.count($templates).' exercise templates.');
    }

    private function seedExercises(): void
    {
        $exercises = $this->loadFile('exercises.php');

        foreach ($exercises as $exercise) {
            $tags = $exercise['tags'] ?? [];

            $model = Exercise::updateOrCreate(
                ['name' => $exercise['name']],
                [
                    'category_id' => $this->resolveTagKey($exercise['category']),
                    'template_id' => $exercise['template'] !== null
                        ? $this->resolveTemplateName($exercise['template'])
                        : null,
                    'video_url' => $exercise['video_url'],
                    'instructions' => $exercise['instructions'],
                    'config' => $exercise['config'],
                ],
            );

            if (! empty($tags)) {
                $syncData = [];
                foreach ($tags as $tag) {
                    $syncData[$this->resolveTagKey($tag['tag'])] = ['sort' => $tag['sort']];
                }
                $model->tags()->sync($syncData);
            }

            $this->exerciseLookup[$exercise['name']] = $model->id;
        }

        $this->command->info('Imported '.count($exercises).' exercises.');
    }

    private function seedExerciseExternals(): void
    {
        $externals = $this->loadFile('exercise_externals.php');

        foreach ($externals as $external) {
            $tags = $external['tags'] ?? [];

            $model = ExerciseExternal::updateOrCreate(
                ['source' => $external['source'], 'name' => $external['name']],
                [
                    'video_url' => $external['video_url'],
                    'category_id' => $external['category'] !== null
                        ? $this->resolveTagKey($external['category'])
                        : null,
                ],
            );

            if (! empty($tags)) {
                $syncData = [];
                foreach ($tags as $tag) {
                    $syncData[$this->resolveTagKey($tag['tag'])] = ['sort' => $tag['sort']];
                }
                $model->tags()->sync($syncData);
            }
        }

        $this->command->info('Imported '.count($externals).' exercise externals.');
    }

    private function seedExercisePrograms(): void
    {
        $programs = $this->loadFile('exercise_programs.php');

        foreach ($programs as $program) {
            $exercises = $program['exercises'] ?? [];
            $config = $this->remapProgramConfigForImport($program['config'] ?? null);

            $attributes = [
                'exercise_category_id' => $program['exercise_category'] !== null
                    ? $this->resolveTagKey($program['exercise_category'])
                    : null,
                'sort' => $program['sort'],
            ];

            if ($config !== null) {
                $attributes['config'] = $config;
            }

            $model = ExerciseProgram::updateOrCreate(
                ['name' => $program['name'], 'parent_type' => null],
                $attributes,
            );

            if (! empty($exercises)) {
                $syncData = [];
                foreach ($exercises as $exercise) {
                    $syncData[$this->resolveExerciseName($exercise['exercise'])] = ['sort' => $exercise['sort']];
                }
                $model->exercises()->sync($syncData);
            }

            $this->programLookup[$program['name']] = $model->id;
        }

        foreach ($programs as $program) {
            $warmUp = $program['warm_up_program'] ?? null;
            $warmDown = $program['warm_down_program'] ?? null;

            if ($warmUp !== null || $warmDown !== null) {
                ExerciseProgram::where('id', $this->programLookup[$program['name']])
                    ->update([
                        'warm_up_program_id' => $warmUp !== null ? $this->resolveProgramName($warmUp) : null,
                        'warm_down_program_id' => $warmDown !== null ? $this->resolveProgramName($warmDown) : null,
                    ]);
            }
        }

        $this->command->info('Imported '.count($programs).' exercise programs.');
    }

    private function seedExercisePlans(): void
    {
        $plans = $this->loadFile('exercise_plans.php');

        foreach ($plans as $plan) {
            $config = $this->remapPlanConfigForImport($plan['config'] ?? null);

            $attributes = [];
            if ($config !== null) {
                $attributes['config'] = $config;
            }

            ExercisePlan::updateOrCreate(
                ['name' => $plan['name']],
                $attributes,
            );
        }

        $this->command->info('Imported '.count($plans).' exercise plans.');
    }

    private function seedUserGroups(): void
    {
        $groups = $this->loadFile('user_groups.php');

        foreach ($groups as $group) {
            $model = UserGroup::updateOrCreate(
                ['name' => $group['name']],
                ['config' => $group['config']],
            );

            $this->groupLookup[$group['name']] = $model->id;
        }

        $this->command->info('Imported '.count($groups).' user groups.');
    }

    private function seedUsers(): void
    {
        $users = $this->loadFile('users.php');

        foreach ($users as $user) {
            $groups = $user['groups'] ?? [];

            $model = User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'type' => $user['type'],
                    'forename' => $user['forename'],
                    'surname' => $user['surname'],
                    'phone' => $user['phone'],
                    'password' => $user['password'],
                    'gender' => $user['gender'] ?? null,
                    'date_of_birth' => $user['date_of_birth'] ?? null,
                    'color' => $user['color'] ?? null,
                    'config' => $user['config'],
                ],
            );

            if (! empty($groups)) {
                $syncData = [];
                foreach ($groups as $group) {
                    $syncData[$this->resolveGroupName($group['group'])] = ['sort' => $group['sort']];
                }
                $model->groups()->sync($syncData);
            }

            $this->userLookup[$user['email']] = $model->id;
        }

        $this->command->info('Imported '.count($users).' users.');
    }

    private function seedTrainingPrograms(): void
    {
        $trainingPrograms = $this->loadFile('training_programs.php');

        foreach ($trainingPrograms as $tp) {
            $groupId = $this->resolveGroupName($tp['group']);
            $programData = $tp['program'] ?? null;

            if ($programData === null) {
                continue;
            }

            $config = $this->remapProgramConfigForImport($programData['config'] ?? null);

            $programAttributes = [
                'name' => $programData['name'],
                'exercise_category_id' => ($programData['exercise_category'] ?? null) !== null
                    ? $this->resolveTagKey($programData['exercise_category'])
                    : null,
                'sort' => $programData['sort'],
                'parent_type' => TrainingProgram::class,
            ];

            if ($config !== null) {
                $programAttributes['config'] = $config;
            }

            $program = ExerciseProgram::create($programAttributes);

            $exercises = $programData['exercises'] ?? [];
            if (! empty($exercises)) {
                $syncData = [];
                foreach ($exercises as $exercise) {
                    $syncData[$this->resolveExerciseName($exercise['exercise'])] = ['sort' => $exercise['sort']];
                }
                $program->exercises()->sync($syncData);
            }

            $trainingProgram = TrainingProgram::create([
                'group_id' => $groupId,
                'exercise_program_id' => $program->id,
                'sort' => $tp['sort'],
            ]);

            $program->update(['parent_id' => $trainingProgram->id]);

            $warmUp = $programData['warm_up_program'] ?? null;
            $warmDown = $programData['warm_down_program'] ?? null;
            if ($warmUp !== null || $warmDown !== null) {
                $program->update([
                    'warm_up_program_id' => $warmUp !== null ? $this->resolveProgramName($warmUp) : null,
                    'warm_down_program_id' => $warmDown !== null ? $this->resolveProgramName($warmDown) : null,
                ]);
            }

            $key = $tp['group'].'|'.$programData['name'];
            $this->trainingProgramLookup[$key] = $trainingProgram->id;
        }

        $this->command->info('Imported '.count($trainingPrograms).' training programs.');
    }

    private function seedTrainingProgramBlocks(): void
    {
        $blocks = $this->loadFile('training_program_blocks.php');

        foreach ($blocks as $block) {
            $model = TrainingProgramBlock::create([
                'group_id' => $block['group'] !== null
                    ? $this->resolveGroupName($block['group'])
                    : null,
                'user_id' => $block['user'] !== null
                    ? $this->resolveUserEmail($block['user'])
                    : null,
                'category_id' => $block['category'] !== null
                    ? $this->resolveTagKey($block['category'])
                    : null,
                'type' => $block['type'],
                'start' => $block['start'],
                'end' => $block['end'],
                'note' => $block['note'],
                'color' => $block['color'],
                'config' => $block['config'],
                'active' => $block['active'],
            ]);

            $this->blockIdMap[$block['id']] = $model->id;
        }

        foreach ($blocks as $block) {
            if ($block['parent_id'] !== null && isset($this->blockIdMap[$block['id']])) {
                TrainingProgramBlock::where('id', $this->blockIdMap[$block['id']])
                    ->update([
                        'parent_id' => $this->blockIdMap[$block['parent_id']] ?? null,
                    ]);
            }
        }

        $this->command->info('Imported '.count($blocks).' training program blocks.');
    }

    private function seedTrainingProgramSlots(): void
    {
        $slots = $this->loadFile('training_program_slots.php');

        foreach ($slots as $slot) {
            $group = $slot['training_program_group'] ?? null;
            $program = $slot['training_program_exercise_program'] ?? null;

            if ($group === null || $program === null) {
                $this->command->warn('Training program slot missing group or program reference, skipping.');

                continue;
            }

            $key = $group.'|'.$program;
            $trainingProgramId = $this->trainingProgramLookup[$key] ?? null;

            if ($trainingProgramId === null) {
                $this->command->warn("Training program not found for slot: {$key}, skipping.");

                continue;
            }

            TrainingProgramSlot::create([
                'training_program_id' => $trainingProgramId,
                'user_id' => $slot['user'] !== null
                    ? $this->resolveUserEmail($slot['user'])
                    : null,
                'datetime' => $slot['datetime'],
            ]);
        }

        $this->command->info('Imported '.count($slots).' training program slots.');
    }

    private function seedMetricSubmissions(): void
    {
        $submissions = $this->loadFile('metric_submissions.php');

        foreach ($submissions as $submission) {
            $ownerId = null;
            $ownerType = $submission['owner_type'] ?? null;
            $ownerRef = $submission['owner_ref'] ?? null;

            if ($ownerType !== null && $ownerRef !== null) {
                if ($ownerType === User::class) {
                    $ownerId = $this->resolveUserEmail($ownerRef);
                } elseif ($ownerType === TrainingProgramBlock::class) {
                    $ownerId = $this->blockIdMap[(int) $ownerRef] ?? null;
                }
            }

            $model = MetricSubmission::create([
                'user_id' => $this->resolveUserEmail($submission['user']),
                'metric' => $submission['metric'],
                'recorded_by' => $submission['recorded_by'] !== null
                    ? $this->resolveUserEmail($submission['recorded_by'])
                    : null,
                'recorded_at' => $submission['recorded_at'],
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
            ]);

            foreach ($submission['values'] ?? [] as $value) {
                MetricValue::create([
                    'submission_id' => $model->id,
                    'field' => $value['field'],
                    'value' => $value['value'],
                ]);
            }
        }

        $this->command->info('Imported '.count($submissions).' metric submissions.');
    }

    private function resolveTagKey(string $key): int
    {
        if (! isset($this->tagLookup[$key])) {
            throw new \RuntimeException("Tag key '{$key}' not found. Ensure tags are seeded first.");
        }

        return $this->tagLookup[$key];
    }

    private function resolveTemplateName(string $name): int
    {
        if (! isset($this->templateLookup[$name])) {
            throw new \RuntimeException("Exercise template '{$name}' not found. Ensure templates are seeded first.");
        }

        return $this->templateLookup[$name];
    }

    private function resolveExerciseName(string $name): int
    {
        if (! isset($this->exerciseLookup[$name])) {
            throw new \RuntimeException("Exercise '{$name}' not found. Ensure exercises are seeded first.");
        }

        return $this->exerciseLookup[$name];
    }

    private function resolveGroupName(string $name): int
    {
        if (! isset($this->groupLookup[$name])) {
            throw new \RuntimeException("User group '{$name}' not found. Ensure user groups are seeded first.");
        }

        return $this->groupLookup[$name];
    }

    private function resolveUserEmail(string $email): int
    {
        if (! isset($this->userLookup[$email])) {
            throw new \RuntimeException("User '{$email}' not found. Ensure users are seeded first.");
        }

        return $this->userLookup[$email];
    }

    private function resolveProgramName(string $name): int
    {
        if (! isset($this->programLookup[$name])) {
            throw new \RuntimeException("Exercise program '{$name}' not found. Ensure exercise programs are seeded first.");
        }

        return $this->programLookup[$name];
    }

    /** @return array<string, mixed>|null */
    private function remapProgramConfigForImport(?array $config): ?array
    {
        if ($config === null) {
            return null;
        }

        if (! empty($config['exercises'])) {
            $remapped = [];
            foreach ($config['exercises'] as $exerciseName => $overrides) {
                $newId = $this->exerciseLookup[$exerciseName] ?? null;
                if ($newId !== null) {
                    $remapped[$newId] = $overrides;
                }
            }
            $config['exercises'] = $remapped;
        }

        if (! empty($config['userExercises'])) {
            $remapped = [];
            foreach ($config['userExercises'] as $userEmail => $exercises) {
                $userId = $this->userLookup[$userEmail] ?? null;
                if ($userId === null) {
                    continue;
                }
                $remapped[$userId] = [];
                foreach ($exercises as $exerciseName => $overrides) {
                    $exId = $this->exerciseLookup[$exerciseName] ?? null;
                    if ($exId !== null) {
                        $remapped[$userId][$exId] = $overrides;
                    }
                }
            }
            $config['userExercises'] = $remapped;
        }

        return $config;
    }

    /** @return array<string, mixed>|null */
    private function remapPlanConfigForImport(?array $config): ?array
    {
        if ($config === null) {
            return null;
        }

        $weeks = $config['schedule']['weeks'] ?? [];
        foreach ($weeks as &$week) {
            $slots = $week['slots'] ?? [];
            foreach ($slots as &$slot) {
                $slot['programs'] = array_map(
                    fn (string $name) => $this->resolveProgramName($name),
                    $slot['programs'] ?? [],
                );
            }
            $week['slots'] = $slots;
        }
        $config['schedule']['weeks'] = $weeks;

        return $config;
    }

    private function resolveImportPath(): string
    {
        $basePath = base_path('import/database');

        $directories = collect(\Illuminate\Support\Facades\File::directories($basePath))
            ->sort()
            ->values();

        if ($directories->isEmpty()) {
            throw new \RuntimeException('No export directories found in import/database/');
        }

        return $directories->last();
    }

    /** @return array<int, array<string, mixed>> */
    private function loadFile(string $filename): array
    {
        $path = $this->importPath.'/'.$filename;

        if (! file_exists($path)) {
            $this->command->warn("File not found: {$path}, skipping.");

            return [];
        }

        return require $path;
    }
}
