<?php

namespace Database\Seeders;

use App\Models\Athlete\MetricSubmission;
use App\Models\Athlete\MetricValue;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseExternal;
use App\Models\Exercise\ExercisePlan;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Models\Exercise\ExerciseTemplate;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionMaterializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseImportSeeder extends Seeder
{
    private string $importPath;

    public function run(): void
    {
        $this->importPath = $this->resolveImportPath();

        $this->command->info("Importing from: {$this->importPath}");

        Model::unguard();

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            Model::withoutEvents(function (): void {
                $this->seedTags();
                $this->seedExerciseTemplates();
                $this->seedExercises();
                $this->seedExerciseExternals();
                $this->seedExercisePrograms();
                $this->normalizeLegacyWarmUpPrograms();
                $this->seedExercisePlans();
                $this->seedUserGroups();
                $this->seedUsers();
                $this->seedTrainingPrograms();
                $this->seedTrainingProgramBlocks();
                $this->seedTrainingProgramSlots();
                $this->seedMetricSubmissions();
            });

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $this->materializeTrainingSessions();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            Model::reguard();
        }
    }

    private function seedTags(): void
    {
        $tags = $this->loadFile('tags.php');

        foreach ($tags as $tag) {
            Tag::create([
                'id' => $tag['id'],
                'parent_id' => $tag['parent_id'],
                'scope' => $tag['scope'],
                'name' => $tag['name'],
                'short_name' => $tag['short_name'],
                'slug' => $tag['slug'],
                'sort_order' => $tag['sort_order'],
                'color' => $tag['color'] ?? null,
                'default_exercise_template_id' => $tag['default_exercise_template_id'] ?? null,
                'deleted_at' => $tag['deleted_at'] ?? null,
            ]);
        }

        $this->command->info('Imported '.count($tags).' tags.');
    }

    private function seedExerciseTemplates(): void
    {
        $templates = $this->loadFile('exercise_templates.php');

        foreach ($templates as $template) {
            ExerciseTemplate::create([
                'id' => $template['id'],
                'name' => $template['name'],
                'config' => $template['config'],
                'deleted_at' => $template['deleted_at'] ?? null,
            ]);
        }

        $this->command->info('Imported '.count($templates).' exercise templates.');
    }

    private function seedExercises(): void
    {
        $exercises = $this->loadFile('exercises.php');

        foreach ($exercises as $exercise) {
            Exercise::create([
                'id' => $exercise['id'],
                'name' => $exercise['name'],
                'category_id' => $exercise['category_id'],
                'template_id' => $exercise['template_id'],
                'video_url' => $exercise['video_url'],
                'instructions' => $exercise['instructions'],
                'config' => $exercise['config'],
                'deleted_at' => $exercise['deleted_at'] ?? null,
            ]);

            $tags = $exercise['tags'] ?? [];
            if (! empty($tags)) {
                $syncData = [];
                foreach ($tags as $tag) {
                    $syncData[$tag['tag_id']] = ['sort' => $tag['sort']];
                }
                Exercise::withTrashed()->find($exercise['id'])->tags()->sync($syncData);
            }
        }

        $this->command->info('Imported '.count($exercises).' exercises.');
    }

    private function seedExerciseExternals(): void
    {
        $externals = $this->loadFile('exercise_externals.php');

        foreach ($externals as $external) {
            ExerciseExternal::create([
                'id' => $external['id'],
                'source' => $external['source'],
                'name' => $external['name'],
                'video_url' => $external['video_url'],
                'category_id' => $external['category_id'],
                'deleted_at' => $external['deleted_at'] ?? null,
            ]);

            $tags = $external['tags'] ?? [];
            if (! empty($tags)) {
                $syncData = [];
                foreach ($tags as $tag) {
                    $syncData[$tag['tag_id']] = ['sort' => $tag['sort']];
                }
                ExerciseExternal::withTrashed()->find($external['id'])->tags()->sync($syncData);
            }
        }

        $this->command->info('Imported '.count($externals).' exercise externals.');
    }

    private function seedExercisePrograms(): void
    {
        $programs = $this->loadFile('exercise_programs.php');

        foreach ($programs as $program) {
            $attributes = [
                'id' => $program['id'],
                'parent_type' => $program['parent_type'],
                'parent_id' => $program['parent_id'],
                'name' => $program['name'],
                'type' => $program['type'] ?? 'program',
                'exercise_category_id' => $program['exercise_category_id'],
                'warm_up_program_id' => $program['warm_up_program_id'],
                'warm_down_program_id' => $program['warm_down_program_id'],
                'sort' => $program['sort'],
                'deleted_at' => $program['deleted_at'] ?? null,
            ];

            if ($program['config'] !== null) {
                $attributes['config'] = $program['config'];
            }

            ExerciseProgram::create($attributes);

            $exercises = $program['exercises'] ?? [];
            if (! empty($exercises)) {
                foreach ($exercises as $exercise) {
                    DB::table('exercise_program_exercises')->insert([
                        'id' => $exercise['id'] ?? null,
                        'exercise_program_id' => $program['id'],
                        'exercise_id' => $exercise['exercise_id'],
                        'sort' => $exercise['sort'],
                        'group' => $exercise['group'] ?? null,
                        'type' => $exercise['type'] ?? 'main',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Imported '.count($programs).' exercise programs.');
    }

    private function seedExercisePlans(): void
    {
        $plans = $this->loadFile('exercise_plans.php');

        foreach ($plans as $plan) {
            ExercisePlan::create([
                'id' => $plan['id'],
                'name' => $plan['name'],
                'config' => $plan['config'],
                'deleted_at' => $plan['deleted_at'] ?? null,
            ]);
        }

        $this->command->info('Imported '.count($plans).' exercise plans.');
    }

    private function normalizeLegacyWarmUpPrograms(): void
    {
        $warmUpCategoryId = 302;

        $warmUpCategoryExists = DB::table('tags')->where('id', $warmUpCategoryId)->exists();

        if (! $warmUpCategoryExists) {
            return;
        }

        $legacyWarmUpProgramIds = DB::table('exercise_programs')
            ->where('exercise_category_id', $warmUpCategoryId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $consumers = DB::table('exercise_programs')
            ->select('id', 'warm_up_program_id', 'config')
            ->whereNotNull('warm_up_program_id')
            ->orderBy('id')
            ->get();

        foreach ($consumers as $program) {
            $sourceProgramId = $program->warm_up_program_id;

            if ($sourceProgramId === null) {
                continue;
            }

            $sourceProgram = DB::table('exercise_programs')
                ->select('id', 'config', 'exercise_category_id')
                ->where('id', $sourceProgramId)
                ->first();

            if ($sourceProgram === null) {
                DB::table('exercise_programs')
                    ->where('id', $program->id)
                    ->update([
                        'warm_up_program_id' => null,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            if ((int) $sourceProgram->exercise_category_id !== $warmUpCategoryId) {
                continue;
            }

            $legacyWarmUpProgramIds[] = (int) $sourceProgramId;

            $sourcePivots = DB::table('exercise_program_exercises')
                ->where('exercise_program_id', $sourceProgramId)
                ->where('type', 'main')
                ->orderBy('sort')
                ->orderBy('id')
                ->get();

            $consumerConfig = $this->decodeProgramConfig($program->config);

            $existingWarmUpPivotIds = DB::table('exercise_program_exercises')
                ->where('exercise_program_id', $program->id)
                ->where('type', 'warm_up')
                ->pluck('id')
                ->all();

            foreach ($existingWarmUpPivotIds as $pivotId) {
                unset($consumerConfig['exercises'][(string) $pivotId], $consumerConfig['exercises'][$pivotId]);

                foreach (array_keys($consumerConfig['userExercises'] ?? []) as $userId) {
                    unset(
                        $consumerConfig['userExercises'][$userId][(string) $pivotId],
                        $consumerConfig['userExercises'][$userId][$pivotId],
                    );
                }
            }

            DB::table('exercise_program_exercises')
                ->where('exercise_program_id', $program->id)
                ->where('type', 'warm_up')
                ->delete();

            $sourceKeyMap = [];

            foreach ($sourcePivots as $pivot) {
                $newPivotId = DB::table('exercise_program_exercises')->insertGetId([
                    'exercise_program_id' => $program->id,
                    'exercise_id' => $pivot->exercise_id,
                    'sort' => $pivot->sort,
                    'group' => $pivot->group,
                    'type' => 'warm_up',
                    'created_at' => $pivot->created_at,
                    'updated_at' => now(),
                ]);

                if (! isset($sourceKeyMap[$pivot->exercise_id])) {
                    $sourceKeyMap[$pivot->exercise_id] = [];
                }

                $sourceKeyMap[$pivot->exercise_id][] = $newPivotId;
            }

            $mergedConfig = $this->mergeProgramConfigs(
                $consumerConfig,
                $this->remapProgramConfigKeys($this->decodeProgramConfig($sourceProgram->config), $sourceKeyMap)
            );

            DB::table('exercise_programs')
                ->where('id', $program->id)
                ->update([
                    'config' => $mergedConfig === [] ? null : json_encode($mergedConfig),
                    'warm_up_program_id' => null,
                    'updated_at' => now(),
                ]);
        }

        $legacyWarmUpProgramIds = array_values(array_unique(array_map('intval', $legacyWarmUpProgramIds)));

        if ($legacyWarmUpProgramIds !== []) {
            DB::table('exercise_programs')
                ->whereIn('id', $legacyWarmUpProgramIds)
                ->update([
                    'type' => ExerciseProgramTypeEnum::WarmUp->value,
                    'exercise_category_id' => 7,
                    'updated_at' => now(),
                ]);
        }

        $warmUpCategoryStillReferenced = DB::table('exercise_programs')->where('exercise_category_id', $warmUpCategoryId)->exists()
            || DB::table('exercises')->where('category_id', $warmUpCategoryId)->exists()
            || DB::table('exercises_external')->where('category_id', $warmUpCategoryId)->exists();

        if (! $warmUpCategoryStillReferenced) {
            DB::table('tags')
                ->where('id', $warmUpCategoryId)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $this->command->info('Normalized legacy warm-up programs.');
    }

    private function decodeProgramConfig(?string $config): array
    {
        return $config ? (json_decode($config, true) ?: []) : [];
    }

    private function remapProgramConfigKeys(array $config, array $keyMap): array
    {
        $config['exercises'] = $this->remapProgramOverrideBag($config['exercises'] ?? [], $keyMap);

        $userExercises = [];
        foreach (($config['userExercises'] ?? []) as $userId => $overrides) {
            $userExercises[$userId] = $this->remapProgramOverrideBag($overrides, $keyMap);
        }
        $config['userExercises'] = $userExercises;

        return $config;
    }

    private function remapProgramOverrideBag(array $overrides, array $keyMap): array
    {
        $remapped = [];
        $position = [];

        foreach ($overrides as $legacyKey => $data) {
            $legacyId = (int) $legacyKey;
            $targetIds = $keyMap[$legacyId] ?? null;

            if ($targetIds === null || $targetIds === []) {
                continue;
            }

            $currentPosition = $position[$legacyId] ?? 0;
            $targetId = $targetIds[min($currentPosition, count($targetIds) - 1)];
            $position[$legacyId] = $currentPosition + 1;
            $remapped[$targetId] = $data;
        }

        return $remapped;
    }

    private function mergeProgramConfigs(array $target, array $source): array
    {
        $target['exercises'] = array_merge(
            $target['exercises'] ?? [],
            $source['exercises'] ?? [],
        );

        $mergedUserExercises = $target['userExercises'] ?? [];
        foreach (($source['userExercises'] ?? []) as $userId => $overrides) {
            $mergedUserExercises[$userId] = array_merge(
                $mergedUserExercises[$userId] ?? [],
                $overrides
            );
        }
        $target['userExercises'] = $mergedUserExercises;

        return $target;
    }

    private function seedUserGroups(): void
    {
        $groups = $this->loadFile('user_groups.php');

        foreach ($groups as $group) {
            UserGroup::create([
                'id' => $group['id'],
                'name' => $group['name'],
                'config' => $group['config'],
                'deleted_at' => $group['deleted_at'] ?? null,
            ]);
        }

        $this->command->info('Imported '.count($groups).' user groups.');
    }

    private function seedUsers(): void
    {
        $users = $this->loadFile('users.php');

        foreach ($users as $user) {
            User::create([
                'id' => $user['id'],
                'type' => $user['type'],
                'forename' => $user['forename'],
                'surname' => $user['surname'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'password' => $user['password'],
                'gender' => $user['gender'] ?? null,
                'date_of_birth' => $user['date_of_birth'] ?? null,
                'color' => $user['color'] ?? null,
                'config' => $user['config'],
                'deleted_at' => $user['deleted_at'] ?? null,
            ]);

            $groups = $user['groups'] ?? [];
            if (! empty($groups)) {
                $syncData = [];
                foreach ($groups as $group) {
                    $syncData[$group['group_id']] = ['sort' => $group['sort']];
                }
                User::withTrashed()->find($user['id'])->groups()->sync($syncData);
            }
        }

        $this->command->info('Imported '.count($users).' users.');
    }

    private function seedTrainingPrograms(): void
    {
        $trainingPrograms = $this->loadFile('training_programs.php');

        foreach ($trainingPrograms as $tp) {
            TrainingProgram::create([
                'id' => $tp['id'],
                'group_id' => $tp['group_id'],
                'exercise_program_id' => $tp['exercise_program_id'],
                'sort' => $tp['sort'],
            ]);
        }

        $this->command->info('Imported '.count($trainingPrograms).' training programs.');
    }

    private function seedTrainingProgramBlocks(): void
    {
        $blocks = $this->loadFile('training_program_blocks.php');

        foreach ($blocks as $block) {
            TrainingProgramBlock::create([
                'id' => $block['id'],
                'parent_id' => $block['parent_id'],
                'group_id' => $block['group_id'],
                'user_id' => $block['user_id'],
                'category_id' => $block['category_id'],
                'type' => $block['type'],
                'start' => $block['start'],
                'end' => $block['end'],
                'note' => $block['note'],
                'color' => $block['color'],
                'config' => $block['config'],
                'active' => $block['active'],
                'deleted_at' => $block['deleted_at'] ?? null,
            ]);
        }

        $this->command->info('Imported '.count($blocks).' training program blocks.');
    }

    private function seedTrainingProgramSlots(): void
    {
        $slots = $this->loadFile('training_program_slots.php');

        foreach ($slots as $slot) {
            TrainingProgramSlot::create([
                'id' => $slot['id'],
                'training_program_id' => $slot['training_program_id'],
                'user_id' => $slot['user_id'],
                'datetime' => $slot['datetime'],
            ]);
        }

        $this->command->info('Imported '.count($slots).' training program slots.');
    }

    private function seedMetricSubmissions(): void
    {
        $submissions = $this->loadFile('metric_submissions.php');

        foreach ($submissions as $submission) {
            MetricSubmission::create([
                'id' => $submission['id'],
                'user_id' => $submission['user_id'],
                'metric' => $submission['metric'],
                'recorded_by' => $submission['recorded_by'],
                'recorded_at' => $submission['recorded_at'],
                'owner_type' => $submission['owner_type'],
                'owner_id' => $submission['owner_id'],
                'deleted_at' => $submission['deleted_at'] ?? null,
            ]);

            foreach ($submission['values'] ?? [] as $value) {
                MetricValue::create([
                    'id' => $value['id'],
                    'submission_id' => $submission['id'],
                    'field' => $value['field'],
                    'value' => $value['value'],
                ]);
            }
        }

        $this->command->info('Imported '.count($submissions).' metric submissions.');
    }

    private function materializeTrainingSessions(): void
    {
        $this->command->info('Materializing compiled training sessions...');

        $materializer = app(TrainingSessionMaterializer::class);
        $count = 0;

        TrainingProgramSlot::query()
            ->orderBy('id')
            ->chunkById(100, function ($slots) use ($materializer, &$count): void {
                foreach ($slots as $slot) {
                    $materializer->materialize($slot, force: true);
                    $count++;
                }
            });

        $this->command->info("Materialized {$count} training program slots.");
    }

    private function resolveImportPath(): string
    {
        $basePath = base_path('import/database');

        $directories = collect(File::directories($basePath))
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
