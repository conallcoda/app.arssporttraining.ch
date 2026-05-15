<?php

namespace Database\Seeders;

use App\Data\Coach\Settings\SessionGroupingSetting;
use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Training\Config\ExercisePlanConfig;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseExternal;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Models\Exercise\ExerciseTemplate;
use App\Models\Tag;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use App\Models\Users\UserGroup;
use App\Support\Import\ImportedEmailNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseImportSeeder extends Seeder
{
    private string $importPath;

    private ?string $configuredImportPath = null;

    private bool $preserveImportedEmails = false;

    public function usingImportPath(string $importPath): self
    {
        $this->configuredImportPath = $importPath;

        return $this;
    }

    public function preserveImportedEmails(): self
    {
        $this->preserveImportedEmails = true;

        return $this;
    }

    public function run(): void
    {
        $this->importPath = $this->resolveImportPath();

        $this->command->info("Importing from: {$this->importPath}");

        Model::unguard();

        try {
            $this->setForeignKeyChecks(false);

            Model::withoutEvents(function (): void {
                $this->seedTags();
                $this->seedExerciseTemplates();
                $this->seedExercises();
                $this->seedExerciseExternals();
                $this->seedExercisePrograms();
                $this->normalizeLegacyWarmUpPrograms();
                $this->removeWarmUpContent();
                $this->seedUserGroups();
                $this->seedUsers();
                $this->applyDefaultCoachSessionGrouping();
                $this->normalizeLegacyStartsAtDates();
                $this->normalizeLegacyImportedExerciseConfigs();
            });

            $this->setForeignKeyChecks(true);
        } finally {
            $this->setForeignKeyChecks(true);
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
                'owner_id' => $template['owner_id'] ?? null,
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
                'owner_id' => $exercise['owner_id'] ?? null,
                'name' => $exercise['name'],
                'category_id' => $exercise['category_id'],
                'external_id' => $exercise['external_id'] ?? null,
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
                'owner_id' => $external['owner_id'] ?? null,
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
                'owner_id' => $program['owner_id'] ?? null,
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

            $programModel = ExerciseProgram::create($attributes);

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

            $config = $this->sanitizeImportedProgramConfig($program['config'] ?? null);

            if ($config !== null) {
                $programModel->config = $config;
                $programModel->saveQuietly();
            }
        }

        $this->command->info('Imported '.count($programs).' exercise programs.');
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

    private function removeWarmUpContent(): void
    {
        $warmUpCategoryId = 302;

        $deletedExercises = DB::table('exercises')
            ->where('category_id', $warmUpCategoryId)
            ->delete();

        $deletedExternals = DB::table('exercises_external')
            ->where('category_id', $warmUpCategoryId)
            ->delete();

        DB::table('tags')
            ->where('id', $warmUpCategoryId)
            ->delete();

        $this->command->info("Removed {$deletedExercises} warm-up exercises, {$deletedExternals} warm-up externals, and the warm-up category.");
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
                'owner_id' => $group['owner_id'] ?? null,
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
            $isCoach = ($user['type'] ?? null) === UserTypeEnum::Coach->value;

            $model = User::create([
                'id' => $user['id'],
                'owner_id' => $user['owner_id'] ?? null,
                'type' => $user['type'],
                'forename' => $user['forename'],
                'surname' => $user['surname'],
                'email' => $isCoach
                    ? ($user['email'] ?? null)
                    : ($this->preserveImportedEmails
                    ? ($user['email'] ?? null)
                    : ImportedEmailNormalizer::normalize($user['email'] ?? null)),
                'phone' => $user['phone'],
                'password' => $user['password'],
                'gender' => $user['gender'] ?? null,
                'date_of_birth' => $user['date_of_birth'] ?? null,
                'color' => $user['color'] ?? null,
                'config' => $user['config'],
                'deleted_at' => $user['deleted_at'] ?? null,
            ]);

            if ($isCoach) {
                $model->forceFill([
                    'account_setup_token_hash' => null,
                    'account_setup_sent_at' => null,
                    'account_setup_expires_at' => null,
                    'account_setup_completed_at' => now(),
                ])->saveQuietly();
            }

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

    private function applyDefaultCoachSessionGrouping(): void
    {
        $sessionGrouping = SessionGroupingSetting::from([
            'mode' => SessionGroupingMode::Groups->value,
            'groupSize' => 2,
            'copyValuesAutomatically' => true,
        ])->toArray();

        $updated = 0;

        User::query()
            ->where('type', UserTypeEnum::Coach->value)
            ->orderBy('id')
            ->chunkById(100, function ($coaches) use ($sessionGrouping, &$updated): void {
                foreach ($coaches as $coach) {
                    $coach->config->set('settings.'.SessionGroupingSetting::fieldsetKey(), $sessionGrouping);
                    $coach->saveQuietly();
                    $updated++;
                }
            });

        $this->command->info("Applied default session grouping to {$updated} coaches.");
    }

    private function normalizeLegacyStartsAtDates(): void
    {
        $normalizedPrograms = 0;

        ExerciseProgram::query()
            ->orderBy('id')
            ->chunkById(100, function ($programs) use (&$normalizedPrograms): void {
                foreach ($programs as $program) {
                    $config = $program->config;

                    if (! $config->clearStartsAtDates()) {
                        continue;
                    }

                    $program->config = $config;
                    $program->saveQuietly();
                    $normalizedPrograms++;
                }
            });

        $this->command->info("Normalized legacy starts-at dates for {$normalizedPrograms} exercise programs.");
    }

    private function normalizeLegacyImportedExerciseConfigs(): void
    {
        $normalizedExercises = $this->normalizeConfigBag(Exercise::class);
        $normalizedTemplates = $this->normalizeConfigBag(ExerciseTemplate::class);
        $normalizedPrograms = $this->normalizeConfigBag(ExerciseProgram::class);

        $this->command->info("Normalized imported exercise configs, including legacy grid overrides and apply-per scopes, for {$normalizedExercises} exercises, {$normalizedTemplates} templates, and {$normalizedPrograms} programs.");
    }

    /** @param class-string<Model> $modelClass */
    private function normalizeConfigBag(string $modelClass): int
    {
        $normalizedCount = 0;

        $modelClass::query()
            ->orderBy('id')
            ->chunkById(100, function ($models) use (&$normalizedCount): void {
                foreach ($models as $model) {
                    $original = $model->getRawOriginal('config');
                    $config = $model->config;

                    if (! $config instanceof ExerciseConfig && ! $config instanceof ExercisePlanConfig) {
                        continue;
                    }

                    $normalized = json_encode($config->toArray());

                    if ($original === $normalized) {
                        continue;
                    }

                    $model->config = $config;
                    $model->saveQuietly();
                    $normalizedCount++;
                }
            });

        return $normalizedCount;
    }

    /** @param  array<string, mixed>|null  $config */
    private function sanitizeImportedProgramConfig(?array $config): ?array
    {
        if ($config === null) {
            return $config;
        }

        return ExercisePlanConfig::from($config)->toPersistedArray();
    }

    private function setForeignKeyChecks(bool $enabled): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS='.(int) $enabled);
        }
    }

    private function resolveImportPath(): string
    {
        if ($this->configuredImportPath !== null) {
            return $this->configuredImportPath;
        }

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
