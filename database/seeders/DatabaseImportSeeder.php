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
use App\Training\TrainingSessionMaterializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
                $syncData = [];
                foreach ($exercises as $exercise) {
                    $syncData[$exercise['exercise_id']] = ['sort' => $exercise['sort']];
                }
                ExerciseProgram::withTrashed()->find($program['id'])->exercises()->sync($syncData);
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
