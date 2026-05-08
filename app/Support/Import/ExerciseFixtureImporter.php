<?php

namespace App\Support\Import;

use App\Data\Coach\Settings\SessionGroupingSetting;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Training\Config\ExercisePlanConfig;
use App\Models\Athlete\MetricSubmission;
use App\Models\Athlete\MetricValue;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseExternal;
use App\Models\Exercise\ExercisePlanConfigOverride;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Exercise\ExerciseTemplate;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Models\Users\UserTypeEnum;
use App\Training\TrainingSessionMaterializer;
use App\Training\TrainingSessionStatusService;
use App\Training\TrainingValueSnapshotCodec;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class ExerciseFixtureImporter
{
    private const TEST_USER_EMAIL = 'conall@coda.works';

    private const TEST_USER_PASSWORD = 'Crepeface1';

    private string $fixturePath;

    /** @var array<int, int> */
    private array $userIdMap = [];

    /** @var array<int, int> */
    private array $groupIdMap = [];

    /** @var array<int, int> */
    private array $templateIdMap = [];

    /** @var array<int, int> */
    private array $externalIdMap = [];

    /** @var array<int, int> */
    private array $exerciseIdMap = [];

    /** @var array<int, int> */
    private array $programIdMap = [];

    /** @var array<int, int> */
    private array $trainingProgramIdMap = [];

    /** @var array<int, int> */
    private array $blockIdMap = [];

    /** @var array<int, int> */
    private array $slotIdMap = [];

    /** @var array<int, int> */
    private array $programExerciseIdMap = [];

    public function __construct(
        private readonly TrainingSessionMaterializer $materializer,
        private readonly TrainingSessionStatusService $statusService,
        private readonly TrainingValueSnapshotCodec $valueCodec,
    ) {}

    public function import(string $fixturePath, Command $command): User
    {
        $this->fixturePath = $fixturePath;
        $this->resetMaps();
        $this->persistTestCredentials();

        return DB::transaction(function () use ($command): User {
            $owner = $this->ensureOwnerUser();

            $this->purgeOwnedData($owner);
            $this->seedTags();
            $this->seedTemplates($owner);
            $this->seedExternals($owner);
            $this->seedUsers($owner);
            $this->seedGroups($owner);
            $this->syncGroupMemberships();
            $this->seedExercises($owner);
            $this->seedPrograms($owner);
            $this->seedTrainingPrograms($owner);
            $this->normalizeScheduledProgramParents();
            $this->seedTrainingBlocks($owner);
            $this->seedTrainingSlots($owner);
            $this->seedMetricSubmissions($owner);
            $this->applyDefaultCoachSessionGrouping();
            $this->materializeTrainingSessions();
            $this->applyRecordedTrainingSessions($owner);

            $command->info('Exercise fixture sandbox reset complete.');
            $command->line('Owner: '.self::TEST_USER_EMAIL);
            $command->line('Athletes created: '.count(array_filter($this->userIdMap, fn (int $id) => $id !== $owner->id)));
            $command->line('Exercises created: '.count($this->exerciseIdMap));
            $command->line('Programs created: '.count($this->programIdMap));
            $command->line('Groups created: '.count($this->groupIdMap));
            $command->line('Scheduled programs created: '.count($this->trainingProgramIdMap));

            return $owner->fresh();
        }, 5);
    }

    private function resetMaps(): void
    {
        $this->userIdMap = [];
        $this->groupIdMap = [];
        $this->templateIdMap = [];
        $this->externalIdMap = [];
        $this->exerciseIdMap = [];
        $this->programIdMap = [];
        $this->trainingProgramIdMap = [];
        $this->blockIdMap = [];
        $this->slotIdMap = [];
        $this->programExerciseIdMap = [];
    }

    private function ensureOwnerUser(): User
    {
        $user = User::withTrashed()->firstOrNew([
            'email' => self::TEST_USER_EMAIL,
        ]);

        $user->forceFill([
            'type' => UserTypeEnum::Coach,
            'forename' => 'Conall',
            'surname' => "O'Reilly",
            'color' => 'blue',
            'password' => Hash::make(self::TEST_USER_PASSWORD),
            'account_setup_token_hash' => null,
            'account_setup_sent_at' => null,
            'account_setup_expires_at' => null,
            'account_setup_completed_at' => now(),
        ]);

        if ($user->trashed()) {
            $user->restore();
        }

        $user->save();

        return $user;
    }

    private function purgeOwnedData(User $owner): void
    {
        $ownerId = (int) $owner->id;
        $ownedUserIds = User::query()->where('owner_id', $ownerId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ownedGroupIds = UserGroup::query()->where('owner_id', $ownerId)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $trainingProgramIds = TrainingProgram::query()
            ->where('owner_id', $ownerId)
            ->orWhereIn('group_id', $ownedGroupIds ?: [0])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $scheduledProgramIds = ExerciseProgram::query()
            ->where('owner_id', $ownerId)
            ->orWhere(function ($query) use ($trainingProgramIds): void {
                $query->where('parent_type', TrainingProgram::class)
                    ->whereIn('parent_id', $trainingProgramIds ?: [0]);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ownedExerciseIds = Exercise::query()->where('owner_id', $ownerId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ownedTemplateIds = ExerciseTemplate::query()->where('owner_id', $ownerId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ownedExternalIds = ExerciseExternal::query()->where('owner_id', $ownerId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ownedTagIds = Tag::query()->where('owner_id', $ownerId)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $blockIds = TrainingProgramBlock::query()
            ->where('owner_id', $ownerId)
            ->orWhereIn('group_id', $ownedGroupIds ?: [0])
            ->orWhereIn('user_id', $ownedUserIds ?: [0])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        MetricSubmission::query()
            ->whereIn('user_id', $ownedUserIds ?: [0])
            ->orWhereIn('recorded_by', array_merge([$ownerId], $ownedUserIds) ?: [0])
            ->orWhere(function ($query) use ($blockIds): void {
                $query->where('owner_type', TrainingProgramBlock::class)
                    ->whereIn('owner_id', $blockIds ?: [0]);
            })
            ->orWhere(function ($query) use ($ownedUserIds, $ownerId): void {
                $query->where('owner_type', User::class)
                    ->whereIn('owner_id', array_merge([$ownerId], $ownedUserIds) ?: [0]);
            })
            ->forceDelete();

        ExercisePlanConfigOverride::query()
            ->where(function ($query) use ($scheduledProgramIds): void {
                $query->where('owner_type', ExerciseProgram::class)
                    ->whereIn('owner_id', $scheduledProgramIds ?: [0]);
            })
            ->orWhereIn('user_id', $ownedUserIds ?: [0])
            ->delete();

        TrainingRevisionBatch::query()
            ->whereIn('changed_by', array_merge([$ownerId], $ownedUserIds) ?: [0])
            ->orWhere(function ($query) use ($ownerId): void {
                $query->where('owner_type', User::class)
                    ->where('owner_id', $ownerId);
            })
            ->orWhere(function ($query) use ($ownedUserIds): void {
                $query->where('owner_type', User::class)
                    ->whereIn('owner_id', $ownedUserIds ?: [0]);
            })
            ->orWhere(function ($query) use ($ownedGroupIds): void {
                $query->where('owner_type', UserGroup::class)
                    ->whereIn('owner_id', $ownedGroupIds ?: [0]);
            })
            ->orWhere(function ($query) use ($trainingProgramIds): void {
                $query->where('owner_type', TrainingProgram::class)
                    ->whereIn('owner_id', $trainingProgramIds ?: [0]);
            })
            ->orWhere(function ($query) use ($blockIds): void {
                $query->where('owner_type', TrainingProgramBlock::class)
                    ->whereIn('owner_id', $blockIds ?: [0]);
            })
            ->orWhere(function ($query) use ($scheduledProgramIds): void {
                $query->where('owner_type', ExerciseProgram::class)
                    ->whereIn('owner_id', $scheduledProgramIds ?: [0]);
            })
            ->delete();

        ExerciseProgram::withTrashed()->whereIn('id', $scheduledProgramIds ?: [0])->forceDelete();
        Exercise::withTrashed()->whereIn('id', $ownedExerciseIds ?: [0])->forceDelete();
        ExerciseTemplate::withTrashed()->whereIn('id', $ownedTemplateIds ?: [0])->forceDelete();
        ExerciseExternal::withTrashed()->whereIn('id', $ownedExternalIds ?: [0])->forceDelete();
        TrainingProgramBlock::withTrashed()->whereIn('id', $blockIds ?: [0])->forceDelete();
        UserGroup::withTrashed()->whereIn('id', $ownedGroupIds ?: [0])->forceDelete();
        User::withTrashed()->whereIn('id', $ownedUserIds ?: [0])->forceDelete();
        Tag::query()->whereIn('id', $ownedTagIds ?: [0])->delete();
    }

    private function seedTags(): void
    {
        foreach ($this->loadFile('tags.php') as $tag) {
            DB::table('tags')->updateOrInsert(
                ['id' => $tag['id']],
                [
                    'parent_id' => $tag['parent_id'],
                    'scope' => $tag['scope'],
                    'name' => $tag['name'],
                    'short_name' => $tag['short_name'],
                    'slug' => $tag['slug'],
                    'sort_order' => $tag['sort_order'],
                    'color' => $tag['color'] ?? null,
                    'owner_id' => null,
                    'default_exercise_template_id' => $tag['default_exercise_template_id'] ?? null,
                    'deleted_at' => $tag['deleted_at'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    private function seedTemplates(User $owner): void
    {
        foreach ($this->loadFile('exercise_templates.php') as $template) {
            $fixtureId = (int) $template['id'];
            $ownerId = $template['owner_id'] ?? null;

            if ($ownerId === null) {
                DB::table('exercise_templates')->updateOrInsert(
                    ['id' => $fixtureId],
                    [
                        'owner_id' => null,
                        'name' => $template['name'],
                        'config' => json_encode($template['config']),
                        'deleted_at' => $template['deleted_at'] ?? null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
                $this->templateIdMap[$fixtureId] = $fixtureId;

                continue;
            }

            $model = ExerciseTemplate::create([
                'owner_id' => $this->resolveOwnerId($owner, $ownerId),
                'name' => $template['name'],
                'config' => $template['config'],
                'deleted_at' => $template['deleted_at'] ?? null,
            ]);

            $this->templateIdMap[$fixtureId] = (int) $model->id;
        }
    }

    private function seedExternals(User $owner): void
    {
        foreach ($this->loadFile('exercise_externals.php') as $external) {
            $fixtureId = (int) $external['id'];
            $ownerId = $external['owner_id'] ?? null;

            if ($ownerId === null) {
                DB::table('exercises_external')->updateOrInsert(
                    ['id' => $fixtureId],
                    [
                        'owner_id' => null,
                        'source' => $external['source'],
                        'name' => $external['name'],
                        'video_url' => $external['video_url'],
                        'category_id' => $external['category_id'],
                        'deleted_at' => $external['deleted_at'] ?? null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
                $this->externalIdMap[$fixtureId] = $fixtureId;

                continue;
            }

            $model = ExerciseExternal::create([
                'owner_id' => $this->resolveOwnerId($owner, $ownerId),
                'source' => $external['source'],
                'name' => $external['name'],
                'video_url' => $external['video_url'],
                'category_id' => $external['category_id'],
                'deleted_at' => $external['deleted_at'] ?? null,
            ]);

            $this->externalIdMap[$fixtureId] = (int) $model->id;
        }
    }

    private function seedUsers(User $owner): void
    {
        foreach ($this->loadFile('users.php') as $user) {
            $fixtureId = (int) $user['id'];

            if (($user['type'] ?? null) === UserTypeEnum::Coach->value && ($user['owner_id'] ?? null) === null) {
                $owner->config = $user['config'] ?? [];
                $owner->save();
                $this->userIdMap[$fixtureId] = (int) $owner->id;
                continue;
            }

            $model = User::create([
                'owner_id' => $this->resolveOwnerId($owner, $user['owner_id'] ?? null),
                'type' => $user['type'],
                'forename' => $user['forename'],
                'surname' => $user['surname'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'password' => filled($user['password'] ?? null)
                    ? Hash::make((string) $user['password'])
                    : null,
                'gender' => $user['gender'] ?? null,
                'date_of_birth' => $user['date_of_birth'] ?? null,
                'color' => $user['color'] ?? null,
                'config' => $user['config'],
                'deleted_at' => $user['deleted_at'] ?? null,
            ]);

            if (($user['type'] ?? null) === UserTypeEnum::Coach->value) {
                $model->forceFill([
                    'account_setup_token_hash' => null,
                    'account_setup_sent_at' => null,
                    'account_setup_expires_at' => null,
                    'account_setup_completed_at' => now(),
                ])->saveQuietly();
            }

            $this->userIdMap[$fixtureId] = (int) $model->id;
        }
    }

    private function seedGroups(User $owner): void
    {
        foreach ($this->loadFile('user_groups.php') as $group) {
            $model = UserGroup::create([
                'owner_id' => $this->resolveOwnerId($owner, $group['owner_id'] ?? null),
                'name' => $group['name'],
                'config' => $group['config'],
                'deleted_at' => $group['deleted_at'] ?? null,
            ]);

            $this->groupIdMap[(int) $group['id']] = (int) $model->id;
        }
    }

    private function syncGroupMemberships(): void
    {
        foreach ($this->loadFile('users.php') as $user) {
            $newUserId = $this->userIdMap[(int) $user['id']] ?? null;

            if ($newUserId === null || ! isset($user['groups'])) {
                continue;
            }

            $syncData = [];

            foreach ($user['groups'] as $group) {
                $mappedGroupId = $this->groupIdMap[(int) $group['group_id']] ?? null;

                if ($mappedGroupId === null) {
                    continue;
                }

                $syncData[$mappedGroupId] = ['sort' => $group['sort']];
            }

            if ($syncData === []) {
                continue;
            }

            User::query()->findOrFail($newUserId)->groups()->sync($syncData);
        }
    }

    private function seedExercises(User $owner): void
    {
        foreach ($this->loadFile('exercises.php') as $exercise) {
            $model = Exercise::create([
                'owner_id' => $this->resolveOwnerId($owner, $exercise['owner_id'] ?? null),
                'name' => $exercise['name'],
                'category_id' => $exercise['category_id'],
                'external_id' => isset($exercise['external_id']) && $exercise['external_id'] !== null
                    ? ($this->externalIdMap[(int) $exercise['external_id']] ?? (int) $exercise['external_id'])
                    : null,
                'template_id' => isset($exercise['template_id']) && $exercise['template_id'] !== null
                    ? ($this->templateIdMap[(int) $exercise['template_id']] ?? (int) $exercise['template_id'])
                    : null,
                'video_url' => $exercise['video_url'],
                'instructions' => $exercise['instructions'],
                'config' => $exercise['config'],
                'deleted_at' => $exercise['deleted_at'] ?? null,
            ]);

            $this->exerciseIdMap[(int) $exercise['id']] = (int) $model->id;

            $syncData = [];
            foreach (($exercise['tags'] ?? []) as $tag) {
                $syncData[(int) $tag['tag_id']] = ['sort' => $tag['sort']];
            }

            if ($syncData !== []) {
                $model->tags()->sync($syncData);
            }
        }
    }

    private function seedPrograms(User $owner): void
    {
        $pivotRows = [];

        foreach ($this->loadFile('exercise_programs.php') as $program) {
            $model = ExerciseProgram::create([
                'owner_id' => $this->resolveOwnerId($owner, $program['owner_id'] ?? null),
                'parent_type' => null,
                'parent_id' => null,
                'name' => $program['name'],
                'type' => $program['type'] ?? 'program',
                'exercise_category_id' => $program['exercise_category_id'],
                'warm_up_program_id' => null,
                'warm_down_program_id' => null,
                'sort' => $program['sort'],
                'config' => [],
                'deleted_at' => $program['deleted_at'] ?? null,
            ]);

            $fixtureId = (int) $program['id'];
            $this->programIdMap[$fixtureId] = (int) $model->id;
            $pivotRows[$fixtureId] = $program['exercises'] ?? [];
        }

        foreach ($this->loadFile('exercise_programs.php') as $program) {
            $model = ExerciseProgram::query()->findOrFail($this->programIdMap[(int) $program['id']]);
            $pivotIdMap = [];

            $model->warm_up_program_id = isset($program['warm_up_program_id']) && $program['warm_up_program_id'] !== null
                ? ($this->programIdMap[(int) $program['warm_up_program_id']] ?? null)
                : null;
            $model->warm_down_program_id = isset($program['warm_down_program_id']) && $program['warm_down_program_id'] !== null
                ? ($this->programIdMap[(int) $program['warm_down_program_id']] ?? null)
                : null;
            $model->save();

            foreach ($pivotRows[(int) $program['id']] as $exercise) {
                $pivot = ExerciseProgramExercise::create([
                    'exercise_program_id' => (int) $model->id,
                    'exercise_id' => $this->exerciseIdMap[(int) $exercise['exercise_id']] ?? (int) $exercise['exercise_id'],
                    'sort' => $exercise['sort'],
                    'group' => $exercise['group'] ?? null,
                    'type' => $exercise['type'] ?? 'main',
                ]);

                if (isset($exercise['id'])) {
                    $this->programExerciseIdMap[(int) $exercise['id']] = (int) $pivot->id;
                    $pivotIdMap[(int) $exercise['id']] = (int) $pivot->id;
                }
            }

            if (($program['config'] ?? null) !== null) {
                $config = ExercisePlanConfig::from($program['config']);
                $config->remapDefaultExerciseOverrides($pivotIdMap);
                $config->remapUserExerciseOverrides($pivotIdMap);
                $model->config = $config;
                $model->save();
            }
        }
    }

    private function seedTrainingPrograms(User $owner): void
    {
        foreach ($this->loadFile('training_programs.php') as $trainingProgram) {
            $model = TrainingProgram::create([
                'owner_id' => $this->resolveOwnerId($owner, $trainingProgram['owner_id'] ?? null),
                'group_id' => $this->groupIdMap[(int) $trainingProgram['group_id']] ?? (int) $trainingProgram['group_id'],
                'exercise_program_id' => $this->programIdMap[(int) $trainingProgram['exercise_program_id']] ?? (int) $trainingProgram['exercise_program_id'],
                'status' => TrainingProgram::normalizeStatus($trainingProgram['status'] ?? null),
                'sort' => $trainingProgram['sort'],
            ]);

            $this->trainingProgramIdMap[(int) $trainingProgram['id']] = (int) $model->id;
        }
    }

    private function normalizeScheduledProgramParents(): void
    {
        foreach ($this->loadFile('training_programs.php') as $trainingProgram) {
            $trainingProgramId = $this->trainingProgramIdMap[(int) $trainingProgram['id']] ?? null;
            $exerciseProgramId = $this->programIdMap[(int) $trainingProgram['exercise_program_id']] ?? null;

            if ($trainingProgramId === null || $exerciseProgramId === null) {
                continue;
            }

            ExerciseProgram::query()
                ->whereKey($exerciseProgramId)
                ->update([
                    'parent_type' => TrainingProgram::class,
                    'parent_id' => $trainingProgramId,
                ]);
        }
    }

    private function seedTrainingBlocks(User $owner): void
    {
        foreach ($this->loadFile('training_program_blocks.php') as $block) {
            $model = TrainingProgramBlock::create([
                'parent_id' => isset($block['parent_id']) && $block['parent_id'] !== null
                    ? ($this->blockIdMap[(int) $block['parent_id']] ?? null)
                    : null,
                'owner_id' => $this->resolveOwnerId($owner, $block['owner_id'] ?? null),
                'group_id' => $this->groupIdMap[(int) $block['group_id']] ?? (int) $block['group_id'],
                'user_id' => isset($block['user_id']) && $block['user_id'] !== null
                    ? ($this->userIdMap[(int) $block['user_id']] ?? null)
                    : null,
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

            $this->blockIdMap[(int) $block['id']] = (int) $model->id;
        }
    }

    private function seedTrainingSlots(User $owner): void
    {
        foreach ($this->loadFile('training_program_slots.php') as $slot) {
            $model = TrainingProgramSlot::create([
                'training_program_id' => $this->trainingProgramIdMap[(int) $slot['training_program_id']] ?? (int) $slot['training_program_id'],
                'user_id' => $this->userIdMap[(int) $slot['user_id']] ?? (int) $slot['user_id'],
                'owner_id' => $this->resolveOwnerId($owner, $slot['owner_id'] ?? null),
                'datetime' => $slot['datetime'],
                'scheduled_date' => $slot['scheduled_date'] ?? null,
                'status' => $slot['status'] ?? 'pending',
                'compiled_at' => $slot['compiled_at'] ?? null,
                'compiled_version' => $slot['compiled_version'] ?? null,
                'exercise_count' => $slot['exercise_count'] ?? 0,
                'completed_exercise_count' => $slot['completed_exercise_count'] ?? 0,
                'partial_exercise_count' => $slot['partial_exercise_count'] ?? 0,
                'skipped_exercise_count' => $slot['skipped_exercise_count'] ?? 0,
                'pending_exercise_count' => $slot['pending_exercise_count'] ?? 0,
                'has_any_modification' => $slot['has_any_modification'] ?? false,
                'completed_at' => $slot['completed_at'] ?? null,
                'cancelled_at' => $slot['cancelled_at'] ?? null,
            ]);

            $this->slotIdMap[(int) $slot['id']] = (int) $model->id;
        }
    }

    private function seedMetricSubmissions(User $owner): void
    {
        foreach ($this->loadFile('metric_submissions.php') as $submission) {
            $model = MetricSubmission::create([
                'user_id' => $this->userIdMap[(int) $submission['user_id']] ?? (int) $submission['user_id'],
                'metric' => $submission['metric'],
                'recorded_by' => isset($submission['recorded_by']) && $submission['recorded_by'] !== null
                    ? ($this->userIdMap[(int) $submission['recorded_by']] ?? $owner->id)
                    : $owner->id,
                'recorded_at' => $submission['recorded_at'],
                'owner_type' => $this->resolveSubmissionOwnerType($submission['owner_type'] ?? null),
                'owner_id' => $this->resolveSubmissionOwnerId($submission['owner_type'] ?? null, $submission['owner_id'] ?? null),
                'deleted_at' => $submission['deleted_at'] ?? null,
            ]);

            foreach (($submission['values'] ?? []) as $value) {
                MetricValue::create([
                    'submission_id' => (int) $model->id,
                    'field' => $value['field'],
                    'value' => $value['value'],
                ]);
            }
        }
    }

    private function applyDefaultCoachSessionGrouping(): void
    {
        $sessionGrouping = SessionGroupingSetting::from([
            'mode' => SessionGroupingMode::Groups->value,
            'groupSize' => 2,
            'copyValuesAutomatically' => true,
        ])->toArray();

        User::query()
            ->where('type', UserTypeEnum::Coach->value)
            ->where(function ($query): void {
                $query->where('email', self::TEST_USER_EMAIL)
                    ->orWhereIn('id', array_values($this->userIdMap) ?: [0]);
            })
            ->each(function (User $coach) use ($sessionGrouping): void {
                $coach->config->set('settings.'.SessionGroupingSetting::fieldsetKey(), $sessionGrouping);
                $coach->saveQuietly();
            });
    }

    private function materializeTrainingSessions(): void
    {
        TrainingProgramSlot::query()
            ->whereIn('user_id', array_values($this->userIdMap) ?: [0])
            ->orderBy('id')
            ->chunkById(100, function ($slots): void {
                foreach ($slots as $slot) {
                    $this->materializer->materialize($slot, force: true);
                }
            });
    }

    private function applyRecordedTrainingSessions(User $owner): void
    {
        foreach ($this->loadFile('training_session_records.php') as $record) {
            $mappedSlotId = $this->slotIdMap[(int) ($record['slot_id'] ?? 0)] ?? null;

            if ($mappedSlotId === null) {
                continue;
            }

            $slot = TrainingProgramSlot::query()
                ->with(['exercises.exercise', 'exercises.sets.values'])
                ->find($mappedSlotId);

            if (! $slot instanceof TrainingProgramSlot) {
                continue;
            }

            $recordedAt = $slot->datetime->copy()->addMinutes(45);
            $defaultState = (string) ($record['default_state'] ?? '');

            if ($defaultState !== '') {
                foreach ($slot->exercises as $exercise) {
                    $this->applyExerciseState($exercise, $defaultState, $recordedAt, (int) $slot->user_id);
                }
            }

            foreach (($record['exercise_overrides'] ?? []) as $exerciseRecord) {
                $exercise = $slot->exercises->first(function (TrainingProgramSlotExercise $slotExercise) use ($exerciseRecord): bool {
                    if (isset($exerciseRecord['name'])) {
                        return $slotExercise->exercise?->name === $exerciseRecord['name'];
                    }

                    if (isset($exerciseRecord['exercise_id'])) {
                        $fixtureExerciseId = (int) $exerciseRecord['exercise_id'];
                        $mappedExerciseId = $this->exerciseIdMap[$fixtureExerciseId] ?? $fixtureExerciseId;

                        return (int) $slotExercise->exercise_id === (int) $mappedExerciseId;
                    }

                    return false;
                });

                if (! $exercise instanceof TrainingProgramSlotExercise) {
                    continue;
                }

                $exerciseState = (string) ($exerciseRecord['state'] ?? '');

                if ($exerciseState !== '' && empty($exerciseRecord['sets'])) {
                    $this->applyExerciseState($exercise, $exerciseState, $recordedAt, (int) $slot->user_id);
                }

                foreach (($exerciseRecord['sets'] ?? []) as $setRecord) {
                    $set = $exercise->sets->firstWhere('set_number', (int) ($setRecord['number'] ?? 0));

                    if (! $set instanceof TrainingProgramSlotSet) {
                        continue;
                    }

                    $this->applySetRecord($set, $setRecord, $recordedAt, (int) $slot->user_id);
                }
            }

            foreach ($slot->exercises as $exercise) {
                $this->statusService->refreshExerciseState($exercise);
            }
        }
    }

    private function applyExerciseState(
        TrainingProgramSlotExercise $exercise,
        string $state,
        Carbon $recordedAt,
        int $recordedBy,
    ): void {
        foreach ($exercise->sets as $set) {
            $this->applySetState($set, $state, $recordedAt, $recordedBy);
        }
    }

    /**
     * @param  array<string, mixed>  $setRecord
     */
    private function applySetRecord(
        TrainingProgramSlotSet $set,
        array $setRecord,
        Carbon $recordedAt,
        int $recordedBy,
    ): void {
        $state = (string) ($setRecord['state'] ?? 'completed');
        $this->applySetState($set, $state, $recordedAt, $recordedBy);

        if (array_key_exists('note', $setRecord)) {
            $set->forceFill([
                'athlete_note' => $setRecord['note'],
            ])->save();
        }

        foreach (($setRecord['values'] ?? []) as $settingKey => $value) {
            $valueRow = $set->values->firstWhere('setting_key', $settingKey);

            if (! $valueRow instanceof TrainingProgramSlotSetValue) {
                continue;
            }

            $this->applyActualValue($valueRow, $value, $recordedAt, $recordedBy);
        }
    }

    private function applySetState(
        TrainingProgramSlotSet $set,
        string $state,
        Carbon $recordedAt,
        int $recordedBy,
    ): void {
        if ($state === 'skipped') {
            foreach ($set->values as $valueRow) {
                $valueRow->forceFill($this->valueCodec->clearActualValue() + [
                    'is_modified' => false,
                ])->save();
            }

            $set->forceFill([
                'completed_at' => null,
                'skipped_at' => $recordedAt,
            ])->save();

            return;
        }

        if ($state === 'pending') {
            foreach ($set->values as $valueRow) {
                $valueRow->forceFill($this->valueCodec->clearActualValue() + [
                    'is_modified' => false,
                ])->save();
            }

            $set->forceFill([
                'completed_at' => null,
                'skipped_at' => null,
            ])->save();

            return;
        }

        foreach ($set->values as $valueRow) {
            $this->copyPlannedValueToActual($valueRow, $recordedAt, $recordedBy);
        }

        $set->forceFill([
            'completed_at' => $recordedAt,
            'skipped_at' => null,
        ])->save();
    }

    private function applyActualValue(
        TrainingProgramSlotSetValue $valueRow,
        mixed $value,
        Carbon $recordedAt,
        int $recordedBy,
    ): void {
        $normalized = $this->normalizeFixtureValue($valueRow, $value);
        $valueType = $this->resolveFixtureValueType($valueRow, $normalized);
        $plannedValue = $this->valueCodec->extractPlannedValue($valueRow);

        $valueRow->forceFill(
            $this->valueCodec->encodeActualValue($valueType, $normalized, is_array($normalized) ? $normalized : null) + [
                'actual_recorded_by' => $recordedBy,
                'actual_recorded_at' => $recordedAt,
                'actual_source' => 'athlete',
                'actual_is_explicit' => true,
                'unit' => $valueRow->unit,
                'is_modified' => $normalized !== $plannedValue,
            ]
        )->save();
    }

    private function copyPlannedValueToActual(
        TrainingProgramSlotSetValue $valueRow,
        Carbon $recordedAt,
        int $recordedBy,
    ): void {
        $plannedType = $this->valueCodec->extractPlannedType($valueRow);

        if ($plannedType === null) {
            $valueRow->forceFill($this->valueCodec->clearActualValue() + [
                'is_modified' => false,
            ])->save();

            return;
        }

        $plannedValue = $this->valueCodec->extractPlannedValue($valueRow);
        $plannedCanonical = $valueRow->plannedCanonicalValue();

        $valueRow->forceFill(
            $this->valueCodec->encodeActualValue($plannedType, $plannedValue, $plannedCanonical) + [
                'actual_recorded_by' => $recordedBy,
                'actual_recorded_at' => $recordedAt,
                'actual_source' => 'athlete',
                'actual_is_explicit' => false,
                'unit' => $valueRow->unit,
                'is_modified' => false,
            ]
        )->save();
    }

    private function normalizeFixtureValue(TrainingProgramSlotSetValue $valueRow, mixed $value): mixed
    {
        $plannedType = $this->valueCodec->extractPlannedType($valueRow);

        return match ($plannedType) {
            'int' => is_numeric($value) ? (int) $value : $value,
            'decimal' => is_numeric($value) ? round((float) $value, 3) : $value,
            'json' => $value,
            'string' => $value === null ? null : (string) $value,
            default => match (true) {
                is_int($value) => $value,
                is_float($value) => round($value, 3),
                is_numeric($value) && str_contains((string) $value, '.') => round((float) $value, 3),
                is_numeric($value) => (int) $value,
                default => $value === null ? null : (string) $value,
            },
        };
    }

    private function resolveFixtureValueType(TrainingProgramSlotSetValue $valueRow, mixed $value): string
    {
        $plannedType = $this->valueCodec->extractPlannedType($valueRow);

        if ($plannedType !== null) {
            return $plannedType;
        }

        return match (true) {
            is_array($value) => 'json',
            is_float($value) => 'decimal',
            is_int($value) => 'int',
            default => 'string',
        };
    }

    private function persistTestCredentials(): void
    {
        $path = $this->resolveEnvFilePath();
        $content = File::exists($path) ? File::get($path) : '';

        $content = $this->upsertEnvValue($content, 'TEST_USER_EMAIL', self::TEST_USER_EMAIL);
        $content = $this->upsertEnvValue($content, 'TEST_USER_PASSWORD', self::TEST_USER_PASSWORD);

        File::put($path, $content);
    }

    private function resolveEnvFilePath(): string
    {
        $configured = config('app.test_user_env_file');

        if (is_string($configured) && $configured !== '') {
            return str_starts_with($configured, DIRECTORY_SEPARATOR)
                ? $configured
                : base_path($configured);
        }

        return base_path('.env');
    }

    private function upsertEnvValue(string $content, string $key, string $value): string
    {
        $line = $key.'='.$value;

        if (preg_match('/^'.preg_quote($key, '/').'=/m', $content) === 1) {
            return (string) preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $content);
        }

        $trimmed = rtrim($content);

        return $trimmed === ''
            ? $line.PHP_EOL
            : $trimmed.PHP_EOL.$line.PHP_EOL;
    }

    private function resolveOwnerId(User $owner, mixed $fixtureOwnerId): ?int
    {
        if ($fixtureOwnerId === null || $fixtureOwnerId === '') {
            return (int) $owner->id;
        }

        return $this->userIdMap[(int) $fixtureOwnerId] ?? (int) $owner->id;
    }

    private function resolveSubmissionOwnerType(?string $ownerType): ?string
    {
        return match ($ownerType) {
            User::class => User::class,
            TrainingProgramBlock::class => TrainingProgramBlock::class,
            default => $ownerType,
        };
    }

    private function resolveSubmissionOwnerId(?string $ownerType, mixed $ownerId): ?int
    {
        if ($ownerId === null || $ownerId === '') {
            return null;
        }

        return match ($ownerType) {
            User::class => $this->userIdMap[(int) $ownerId] ?? null,
            TrainingProgramBlock::class => $this->blockIdMap[(int) $ownerId] ?? null,
            default => (int) $ownerId,
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function loadFile(string $filename): array
    {
        $path = $this->fixturePath.'/'.$filename;

        if (! File::exists($path)) {
            return [];
        }

        return require $path;
    }
}
