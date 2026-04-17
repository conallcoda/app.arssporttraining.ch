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

    protected $description = 'Export all business data to PHP files using original IDs';

    private string $exportPath;

    public function handle(): int
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $this->exportPath = base_path('import/database/'.$timestamp);

        File::ensureDirectoryExists($this->exportPath);

        $this->info("Exporting to: import/database/{$timestamp}");

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

    private function exportTags(): void
    {
        $tags = Tag::withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'parent_id' => $tag->parent_id,
                'scope' => $tag->scope,
                'name' => $tag->name,
                'short_name' => $tag->short_name,
                'slug' => $tag->slug,
                'sort_order' => $tag->sort_order,
                'color' => $tag->color,
                'default_exercise_template_id' => $tag->default_exercise_template_id,
                'deleted_at' => $tag->deleted_at?->toIso8601String(),
            ])
            ->all();

        $this->writeFile('tags.php', $tags);
        $this->info('Exported '.count($tags).' tags.');
    }

    private function exportExerciseTemplates(): void
    {
        $templates = ExerciseTemplate::withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (ExerciseTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'config' => json_decode($template->getRawOriginal('config'), true),
                'deleted_at' => $template->deleted_at?->toIso8601String(),
            ])
            ->all();

        $this->writeFile('exercise_templates.php', $templates);
        $this->info('Exported '.count($templates).' exercise templates.');
    }

    private function exportExercises(): void
    {
        $exercises = Exercise::withTrashed()
            ->with('tags')
            ->orderBy('id')
            ->get()
            ->map(fn (Exercise $exercise) => [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'category_id' => $exercise->category_id,
                'template_id' => $exercise->template_id,
                'video_url' => $exercise->video_url,
                'instructions' => $exercise->instructions,
                'config' => json_decode($exercise->getRawOriginal('config'), true),
                'tags' => $exercise->tags->map(fn (Tag $tag) => [
                    'tag_id' => $tag->id,
                    'sort' => $tag->pivot->sort,
                ])->all(),
                'deleted_at' => $exercise->deleted_at?->toIso8601String(),
            ])
            ->all();

        $this->writeFile('exercises.php', $exercises);
        $this->info('Exported '.count($exercises).' exercises.');
    }

    private function exportExerciseExternals(): void
    {
        $externals = ExerciseExternal::withTrashed()
            ->with('tags')
            ->orderBy('id')
            ->get()
            ->map(fn (ExerciseExternal $external) => [
                'id' => $external->id,
                'source' => $external->source,
                'name' => $external->name,
                'video_url' => $external->video_url,
                'category_id' => $external->category_id,
                'tags' => $external->tags->map(fn (Tag $tag) => [
                    'tag_id' => $tag->id,
                    'sort' => $tag->pivot->sort,
                ])->all(),
                'deleted_at' => $external->deleted_at?->toIso8601String(),
            ])
            ->all();

        $this->writeFile('exercise_externals.php', $externals);
        $this->info('Exported '.count($externals).' exercise externals.');
    }

    private function exportExercisePrograms(): void
    {
        $programs = ExerciseProgram::withTrashed()
            ->with('exercises')
            ->orderBy('id')
            ->get()
            ->map(fn (ExerciseProgram $program) => [
                'id' => $program->id,
                'parent_type' => $program->parent_type,
                'parent_id' => $program->parent_id,
                'name' => $program->name,
                'type' => $program->type?->value ?? 'program',
                'exercise_category_id' => $program->exercise_category_id,
                'warm_up_program_id' => $program->warm_up_program_id,
                'warm_down_program_id' => $program->warm_down_program_id,
                'sort' => $program->sort,
                'config' => json_decode($program->getRawOriginal('config'), true),
                'exercises' => $program->exercises->map(fn (Exercise $exercise) => [
                    'id' => $exercise->pivot->id,
                    'exercise_id' => $exercise->id,
                    'sort' => $exercise->pivot->sort,
                    'group' => $exercise->pivot->group,
                    'type' => $exercise->pivot->type ?? 'main',
                ])->all(),
                'deleted_at' => $program->deleted_at?->toIso8601String(),
            ])
            ->all();

        $this->writeFile('exercise_programs.php', $programs);
        $this->info('Exported '.count($programs).' exercise programs.');
    }

    private function exportExercisePlans(): void
    {
        $plans = ExercisePlan::withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (ExercisePlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'config' => json_decode($plan->getRawOriginal('config'), true),
                'deleted_at' => $plan->deleted_at?->toIso8601String(),
            ])
            ->all();

        $this->writeFile('exercise_plans.php', $plans);
        $this->info('Exported '.count($plans).' exercise plans.');
    }

    private function exportUserGroups(): void
    {
        $groups = UserGroup::withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (UserGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'config' => json_decode($group->getRawOriginal('config'), true),
                'deleted_at' => $group->deleted_at?->toIso8601String(),
            ])
            ->all();

        $this->writeFile('user_groups.php', $groups);
        $this->info('Exported '.count($groups).' user groups.');
    }

    private function exportUsers(): void
    {
        $users = User::withTrashed()
            ->with('groups')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
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
                    'group_id' => $group->id,
                    'sort' => $group->pivot->sort,
                ])->all(),
                'deleted_at' => $user->deleted_at?->toIso8601String(),
            ])
            ->all();

        $this->writeFile('users.php', $users);
        $this->info('Exported '.count($users).' users.');
    }

    private function exportTrainingPrograms(): void
    {
        $programs = TrainingProgram::query()
            ->orderBy('id')
            ->get()
            ->map(fn (TrainingProgram $tp) => [
                'id' => $tp->id,
                'group_id' => $tp->group_id,
                'exercise_program_id' => $tp->exercise_program_id,
                'sort' => $tp->sort,
            ])
            ->all();

        $this->writeFile('training_programs.php', $programs);
        $this->info('Exported '.count($programs).' training programs.');
    }

    private function exportTrainingProgramBlocks(): void
    {
        $blocks = TrainingProgramBlock::withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (TrainingProgramBlock $block) => [
                'id' => $block->id,
                'parent_id' => $block->parent_id,
                'group_id' => $block->group_id,
                'user_id' => $block->user_id,
                'category_id' => $block->category_id,
                'type' => $block->getRawOriginal('type'),
                'start' => $block->start?->format('Y-m-d'),
                'end' => $block->end?->format('Y-m-d'),
                'note' => $block->note,
                'color' => $block->color,
                'config' => json_decode($block->getRawOriginal('config'), true),
                'active' => $block->active,
                'deleted_at' => $block->deleted_at?->toIso8601String(),
            ])
            ->all();

        $this->writeFile('training_program_blocks.php', $blocks);
        $this->info('Exported '.count($blocks).' training program blocks.');
    }

    private function exportTrainingProgramSlots(): void
    {
        $slots = TrainingProgramSlot::query()
            ->orderBy('id')
            ->get()
            ->map(fn (TrainingProgramSlot $slot) => [
                'id' => $slot->id,
                'training_program_id' => $slot->training_program_id,
                'user_id' => $slot->user_id,
                'datetime' => $slot->datetime?->toIso8601String(),
            ])
            ->all();

        $this->writeFile('training_program_slots.php', $slots);
        $this->info('Exported '.count($slots).' training program slots.');
    }

    private function exportMetricSubmissions(): void
    {
        $submissions = MetricSubmission::withTrashed()
            ->with('values')
            ->orderBy('id')
            ->get()
            ->map(fn (MetricSubmission $submission) => [
                'id' => $submission->id,
                'user_id' => $submission->user_id,
                'metric' => $submission->getRawOriginal('metric'),
                'recorded_by' => $submission->recorded_by,
                'recorded_at' => $submission->recorded_at?->format('Y-m-d'),
                'owner_type' => $submission->owner_type,
                'owner_id' => $submission->owner_id,
                'values' => $submission->values->map(fn (MetricValue $value) => [
                    'id' => $value->id,
                    'field' => $value->field,
                    'value' => $value->value,
                ])->all(),
                'deleted_at' => $submission->deleted_at?->toIso8601String(),
            ])
            ->all();

        $this->writeFile('metric_submissions.php', $submissions);
        $this->info('Exported '.count($submissions).' metric submissions.');
    }

    private function writeFile(string $filename, array $data): void
    {
        $content = "<?php\n\nreturn ".VarExporter::export($data).";\n";

        File::put($this->exportPath.'/'.$filename, $content);
    }
}
