<?php

namespace App\Console\Commands;

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
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\VarExporter\VarExporter;

class ExportDatabaseCommand extends Command
{
    protected $signature = 'db:export';

    protected $description = 'Export tags, exercises, exercise templates, programs, users, and groups to PHP files';

    private string $exportPath;

    /** @var array<int, array{scope: string, slug: string}> */
    private array $tagIdMap = [];

    /** @var array<int, string> */
    private array $templateIdToName = [];

    /** @var array<int, string> */
    private array $exerciseIdToName = [];

    /** @var array<int, string> */
    private array $groupIdToName = [];

    /** @var array<int, string> */
    private array $userIdToEmail = [];

    /** @var array<int, string> */
    private array $planIdToName = [];

    /** @var array<int, string> */
    private array $programIdToName = [];

    public function handle(): int
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $this->exportPath = base_path('import/database/'.$timestamp);

        File::ensureDirectoryExists($this->exportPath);

        $this->info("Exporting to: import/database/{$timestamp}");

        $this->buildLookups();

        $this->exportTags();
        $this->exportExerciseTemplates();
        $this->exportExercises();
        $this->exportExerciseExternals();
        $this->exportExercisePrograms();
        $this->exportExercisePlans();
        $this->exportUserGroups();
        $this->exportUsers();
        $this->exportTrainingPrograms();
        $this->exportTrainingProgramBlocks();
        $this->exportTrainingProgramSlots();
        $this->exportMetricSubmissions();

        $this->newLine();
        $this->info('Export complete!');

        return 0;
    }

    private function buildLookups(): void
    {
        Tag::withTrashed()->each(function (Tag $tag) {
            $this->tagIdMap[$tag->id] = ['scope' => $tag->scope, 'slug' => $tag->slug];
        });

        ExerciseTemplate::withTrashed()->each(function (ExerciseTemplate $template) {
            $this->templateIdToName[$template->id] = $template->name;
        });

        Exercise::withTrashed()->each(function (Exercise $exercise) {
            $this->exerciseIdToName[$exercise->id] = $exercise->name;
        });

        UserGroup::withTrashed()->each(function (UserGroup $group) {
            $this->groupIdToName[$group->id] = $group->name;
        });

        User::withTrashed()->each(function (User $user) {
            $this->userIdToEmail[$user->id] = $user->email;
        });

        ExercisePlan::withTrashed()->each(function (ExercisePlan $plan) {
            $this->planIdToName[$plan->id] = $plan->name;
        });

        ExerciseProgram::withTrashed()->each(function (ExerciseProgram $program) {
            $this->programIdToName[$program->id] = $program->name;
        });
    }

    private function tagKey(int $id): string
    {
        $tag = $this->tagIdMap[$id] ?? null;

        if (! $tag) {
            throw new \RuntimeException("Tag ID {$id} not found.");
        }

        return $tag['scope'].':'.$tag['slug'];
    }

    private function exportTags(): void
    {
        $allTags = Tag::query()
            ->orderBy('scope')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $rootTags = $allTags->whereNull('parent_id');
        $childrenByParentId = $allTags->whereNotNull('parent_id')->groupBy('parent_id');

        $tags = $rootTags->values()
            ->map(fn (Tag $tag) => $this->buildExportTagNode($tag, $childrenByParentId))
            ->all();

        $this->writeFile('tags.php', $tags);
        $this->info('Exported '.count($allTags).' tags.');
    }

    /** @return array<string, mixed> */
    private function buildExportTagNode(Tag $tag, $childrenByParentId): array
    {
        $node = [
            'scope' => $tag->scope,
            'name' => $tag->name,
            'short_name' => $tag->short_name,
            'slug' => $tag->slug,
            'sort_order' => $tag->sort_order,
        ];

        if ($tag->color !== null) {
            $node['color'] = $tag->color;
        }

        $children = $childrenByParentId->get($tag->id);

        if ($children && $children->isNotEmpty()) {
            $node['children'] = $children
                ->map(function (Tag $child) use ($childrenByParentId) {
                    $childNode = $this->buildExportTagNode($child, $childrenByParentId);
                    unset($childNode['scope']);

                    return $childNode;
                })
                ->values()
                ->all();
        }

        return $node;
    }

    private function exportExerciseTemplates(): void
    {
        $templates = ExerciseTemplate::query()
            ->orderBy('name')
            ->get()
            ->map(fn (ExerciseTemplate $template) => [
                'name' => $template->name,
                'config' => json_decode($template->getRawOriginal('config'), true),
            ])
            ->all();

        $this->writeFile('exercise_templates.php', $templates);
        $this->info('Exported '.count($templates).' exercise templates.');
    }

    private function exportExercises(): void
    {
        $exercises = Exercise::query()
            ->with('tags')
            ->orderBy('name')
            ->get()
            ->map(fn (Exercise $exercise) => [
                'name' => $exercise->name,
                'category' => $this->tagKey($exercise->category_id),
                'template' => $exercise->template_id !== null
                    ? ($this->templateIdToName[$exercise->template_id] ?? null)
                    : null,
                'video_url' => $exercise->video_url,
                'instructions' => $exercise->instructions,
                'config' => json_decode($exercise->getRawOriginal('config'), true),
                'tags' => $exercise->tags->map(fn (Tag $tag) => [
                    'tag' => $this->tagKey($tag->id),
                    'sort' => $tag->pivot->sort,
                ])->all(),
            ])
            ->all();

        $this->writeFile('exercises.php', $exercises);
        $this->info('Exported '.count($exercises).' exercises.');
    }

    private function exportExercisePrograms(): void
    {
        $programs = ExerciseProgram::query()
            ->whereNull('parent_type')
            ->with('exercises')
            ->orderBy('name')
            ->get()
            ->map(fn (ExerciseProgram $program) => $this->buildProgramExport($program))
            ->all();

        $this->writeFile('exercise_programs.php', $programs);
        $this->info('Exported '.count($programs).' exercise programs.');
    }

    /** @return array<string, mixed> */
    private function buildProgramExport(ExerciseProgram $program): array
    {
        return [
            'name' => $program->name,
            'exercise_category' => $program->exercise_category_id !== null
                ? $this->tagKey($program->exercise_category_id)
                : null,
            'warm_up_program' => $program->warm_up_program_id !== null
                ? ($this->programIdToName[$program->warm_up_program_id] ?? null)
                : null,
            'warm_down_program' => $program->warm_down_program_id !== null
                ? ($this->programIdToName[$program->warm_down_program_id] ?? null)
                : null,
            'sort' => $program->sort,
            'config' => $this->remapProgramConfigForExport(
                json_decode($program->getRawOriginal('config'), true)
            ),
            'exercises' => $program->exercises->map(fn (Exercise $exercise) => [
                'exercise' => $exercise->name,
                'sort' => $exercise->pivot->sort,
            ])->all(),
        ];
    }

    private function exportUserGroups(): void
    {
        $groups = UserGroup::query()
            ->orderBy('name')
            ->get()
            ->map(fn (UserGroup $group) => [
                'name' => $group->name,
                'config' => json_decode($group->getRawOriginal('config'), true),
            ])
            ->all();

        $this->writeFile('user_groups.php', $groups);
        $this->info('Exported '.count($groups).' user groups.');
    }

    private function exportUsers(): void
    {
        $users = User::query()
            ->with('groups')
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->map(fn (User $user) => [
                'type' => $user->getRawOriginal('type'),
                'forename' => $user->forename,
                'surname' => $user->surname,
                'email' => $user->email,
                'phone' => $user->phone,
                'password' => $user->getRawOriginal('password'),
                'gender' => $user->getRawOriginal('gender'),
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'color' => $user->color,
                'config' => json_decode($user->getRawOriginal('config'), true),
                'groups' => $user->groups->map(fn (UserGroup $group) => [
                    'group' => $group->name,
                    'sort' => $group->pivot->sort,
                ])->all(),
            ])
            ->all();

        $this->writeFile('users.php', $users);
        $this->info('Exported '.count($users).' users.');
    }

    private function exportExerciseExternals(): void
    {
        $externals = ExerciseExternal::query()
            ->with('tags')
            ->orderBy('name')
            ->get()
            ->map(fn (ExerciseExternal $external) => [
                'source' => $external->source,
                'name' => $external->name,
                'video_url' => $external->video_url,
                'category' => $external->category_id !== null
                    ? $this->tagKey($external->category_id)
                    : null,
                'tags' => $external->tags->map(fn (Tag $tag) => [
                    'tag' => $this->tagKey($tag->id),
                    'sort' => $tag->pivot->sort,
                ])->all(),
            ])
            ->all();

        $this->writeFile('exercise_externals.php', $externals);
        $this->info('Exported '.count($externals).' exercise externals.');
    }

    private function exportExercisePlans(): void
    {
        $plans = ExercisePlan::query()
            ->orderBy('name')
            ->get()
            ->map(fn (ExercisePlan $plan) => [
                'name' => $plan->name,
                'config' => $this->remapPlanConfigForExport(
                    json_decode($plan->getRawOriginal('config'), true)
                ),
            ])
            ->all();

        $this->writeFile('exercise_plans.php', $plans);
        $this->info('Exported '.count($plans).' exercise plans.');
    }

    private function exportTrainingPrograms(): void
    {
        $programs = TrainingProgram::query()
            ->with(['program.exercises'])
            ->orderBy('group_id')
            ->orderBy('sort')
            ->get()
            ->map(fn (TrainingProgram $tp) => [
                'group' => $this->groupIdToName[$tp->group_id] ?? null,
                'sort' => $tp->sort,
                'program' => $tp->program !== null
                    ? $this->buildProgramExport($tp->program)
                    : null,
            ])
            ->all();

        $this->writeFile('training_programs.php', $programs);
        $this->info('Exported '.count($programs).' training programs.');
    }

    private function exportTrainingProgramBlocks(): void
    {
        $blocks = TrainingProgramBlock::query()
            ->orderBy('group_id')
            ->orderBy('start')
            ->get()
            ->map(fn (TrainingProgramBlock $block) => [
                'group' => $block->group_id !== null
                    ? ($this->groupIdToName[$block->group_id] ?? null)
                    : null,
                'user' => $block->user_id !== null
                    ? ($this->userIdToEmail[$block->user_id] ?? null)
                    : null,
                'category' => $block->category_id !== null
                    ? $this->tagKey($block->category_id)
                    : null,
                'type' => $block->getRawOriginal('type'),
                'start' => $block->start?->format('Y-m-d'),
                'end' => $block->end?->format('Y-m-d'),
                'note' => $block->note,
                'color' => $block->color,
                'config' => json_decode($block->getRawOriginal('config'), true),
                'active' => $block->active,
                'parent_id' => $block->parent_id,
                'id' => $block->id,
            ])
            ->all();

        $this->writeFile('training_program_blocks.php', $blocks);
        $this->info('Exported '.count($blocks).' training program blocks.');
    }

    private function exportTrainingProgramSlots(): void
    {
        $slots = TrainingProgramSlot::query()
            ->with('trainingProgram.program')
            ->orderBy('datetime')
            ->get()
            ->map(fn (TrainingProgramSlot $slot) => [
                'training_program_group' => $slot->trainingProgram
                    ? ($this->groupIdToName[$slot->trainingProgram->group_id] ?? null)
                    : null,
                'training_program_exercise_program' => $slot->trainingProgram?->program
                    ? $slot->trainingProgram->program->name
                    : null,
                'training_program_sort' => $slot->trainingProgram?->sort,
                'user' => $slot->user_id !== null
                    ? ($this->userIdToEmail[$slot->user_id] ?? null)
                    : null,
                'datetime' => $slot->datetime?->toIso8601String(),
            ])
            ->all();

        $this->writeFile('training_program_slots.php', $slots);
        $this->info('Exported '.count($slots).' training program slots.');
    }

    private function exportMetricSubmissions(): void
    {
        $submissions = MetricSubmission::query()
            ->with('values')
            ->orderBy('user_id')
            ->orderBy('recorded_at')
            ->get()
            ->map(fn (MetricSubmission $submission) => [
                'user' => $this->userIdToEmail[$submission->user_id] ?? null,
                'metric' => $submission->getRawOriginal('metric'),
                'recorded_by' => $submission->recorded_by !== null
                    ? ($this->userIdToEmail[$submission->recorded_by] ?? null)
                    : null,
                'recorded_at' => $submission->recorded_at?->format('Y-m-d'),
                'owner_type' => $submission->owner_type,
                'owner_ref' => $this->resolveMetricOwnerRef($submission->owner_type, $submission->owner_id),
                'values' => $submission->values->map(fn (MetricValue $value) => [
                    'field' => $value->field,
                    'value' => $value->value,
                ])->all(),
            ])
            ->all();

        $this->writeFile('metric_submissions.php', $submissions);
        $this->info('Exported '.count($submissions).' metric submissions.');
    }

    /** @return array<string, mixed>|null */
    private function remapProgramConfigForExport(?array $config): ?array
    {
        if ($config === null) {
            return null;
        }

        if (! empty($config['exercises'])) {
            $remapped = [];
            foreach ($config['exercises'] as $exerciseId => $overrides) {
                $name = $this->exerciseIdToName[(int) $exerciseId] ?? null;
                if ($name !== null) {
                    $remapped[$name] = $overrides;
                }
            }
            $config['exercises'] = $remapped;
        }

        if (! empty($config['userExercises']) && is_array($config['userExercises'])) {
            $remapped = [];
            foreach ($config['userExercises'] as $userId => $exercises) {
                $email = $this->userIdToEmail[(int) $userId] ?? null;
                if ($email === null || ! is_array($exercises)) {
                    continue;
                }
                $remapped[$email] = [];
                foreach ($exercises as $exerciseId => $overrides) {
                    $name = $this->exerciseIdToName[(int) $exerciseId] ?? null;
                    if ($name !== null) {
                        $remapped[$email][$name] = $overrides;
                    }
                }
            }
            $config['userExercises'] = $remapped;
        }

        return $config;
    }

    /** @return array<string, mixed>|null */
    private function remapPlanConfigForExport(?array $config): ?array
    {
        if ($config === null) {
            return null;
        }

        $weeks = $config['schedule']['weeks'] ?? [];
        foreach ($weeks as &$week) {
            $slots = $week['slots'] ?? [];
            foreach ($slots as &$slot) {
                $slot['programs'] = array_map(
                    fn (int $id) => $this->programIdToName[$id] ?? "unknown_{$id}",
                    $slot['programs'] ?? [],
                );
            }
            $week['slots'] = $slots;
        }
        $config['schedule']['weeks'] = $weeks;

        return $config;
    }

    private function resolveMetricOwnerRef(?string $ownerType, ?int $ownerId): mixed
    {
        if ($ownerType === null || $ownerId === null) {
            return null;
        }

        if ($ownerType === User::class) {
            return $this->userIdToEmail[$ownerId] ?? null;
        }

        return $ownerId;
    }

    private function writeFile(string $filename, array $data): void
    {
        $content = "<?php\n\nreturn ".VarExporter::export($data).";\n";

        File::put($this->exportPath.'/'.$filename, $content);
    }
}
