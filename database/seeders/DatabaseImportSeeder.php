<?php

namespace Database\Seeders;

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseTemplate;
use App\Models\ExerciseProgram;
use App\Models\ProgramCategory;
use App\Models\Tag;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Database\Seeder;

class DatabaseImportSeeder extends Seeder
{
    private string $importPath;

    public function run(): void
    {
        $this->importPath = base_path('import/database');

        $this->seedTags();
        $this->seedExerciseTemplates();
        $this->seedExercises();
        $this->seedProgramCategories();
        $this->seedExercisePrograms();
        $this->seedUserGroups();
        $this->seedUsers();
    }

    private function seedTags(): void
    {
        $tags = $this->loadFile('tags.php');

        foreach ($tags as $tag) {
            Tag::withoutTimestamps(fn () => Tag::updateOrCreate(
                ['id' => $tag['id']],
                [
                    'scope' => $tag['scope'],
                    'name' => $tag['name'],
                    'short_name' => $tag['short_name'],
                    'slug' => $tag['slug'],
                    'parent_id' => null,
                    'sort_order' => $tag['sort_order'],
                ],
            ));
        }

        foreach ($tags as $tag) {
            if ($tag['parent_id'] !== null) {
                Tag::withoutTimestamps(fn () => Tag::where('id', $tag['id'])->update([
                    'parent_id' => $tag['parent_id'],
                ]));
            }
        }

        $this->command->info('Imported '.count($tags).' tags.');
    }

    private function seedExerciseTemplates(): void
    {
        $templates = $this->loadFile('exercise_templates.php');

        foreach ($templates as $template) {
            ExerciseTemplate::withoutTimestamps(fn () => ExerciseTemplate::updateOrCreate(
                ['id' => $template['id']],
                [
                    'name' => $template['name'],
                    'config' => $template['config'],
                ],
            ));
        }

        $this->command->info('Imported '.count($templates).' exercise templates.');
    }

    private function seedExercises(): void
    {
        $exercises = $this->loadFile('exercises.php');

        foreach ($exercises as $exercise) {
            $tags = $exercise['tags'] ?? [];
            unset($exercise['tags']);

            $model = Exercise::withoutTimestamps(fn () => Exercise::updateOrCreate(
                ['id' => $exercise['id']],
                [
                    'name' => $exercise['name'],
                    'category_id' => $exercise['category_id'],
                    'template_id' => $exercise['template_id'],
                    'video_url' => $exercise['video_url'],
                    'instructions' => $exercise['instructions'],
                    'config' => $exercise['config'],
                ],
            ));

            if (! empty($tags)) {
                $syncData = [];
                foreach ($tags as $tag) {
                    $syncData[$tag['id']] = ['sort' => $tag['sort']];
                }
                $model->tags()->sync($syncData);
            }
        }

        $this->command->info('Imported '.count($exercises).' exercises.');
    }

    private function seedProgramCategories(): void
    {
        $categories = $this->loadFile('program_categories.php');

        foreach ($categories as $category) {
            ProgramCategory::withoutTimestamps(fn () => ProgramCategory::updateOrCreate(
                ['id' => $category['id']],
                [
                    'name' => $category['name'],
                    'color' => $category['color'],
                    'sort' => $category['sort'],
                    'config' => $category['config'],
                ],
            ));
        }

        $this->command->info('Imported '.count($categories).' program categories.');
    }

    private function seedExercisePrograms(): void
    {
        $programs = $this->loadFile('exercise_programs.php');

        foreach ($programs as $program) {
            $exercises = $program['exercises'] ?? [];
            unset($program['exercises']);

            $model = ExerciseProgram::withoutTimestamps(fn () => ExerciseProgram::updateOrCreate(
                ['id' => $program['id']],
                [
                    'name' => $program['name'],
                    'program_category_id' => $program['program_category_id'],
                    'sort' => $program['sort'],
                ],
            ));

            if (! empty($exercises)) {
                $syncData = [];
                foreach ($exercises as $exercise) {
                    $syncData[$exercise['id']] = ['sort' => $exercise['sort']];
                }
                $model->exercises()->sync($syncData);
            }
        }

        $this->command->info('Imported '.count($programs).' exercise programs.');
    }

    private function seedUserGroups(): void
    {
        $groups = $this->loadFile('user_groups.php');

        foreach ($groups as $group) {
            UserGroup::withoutTimestamps(fn () => UserGroup::updateOrCreate(
                ['id' => $group['id']],
                [
                    'name' => $group['name'],
                    'config' => $group['config'],
                ],
            ));
        }

        $this->command->info('Imported '.count($groups).' user groups.');
    }

    private function seedUsers(): void
    {
        $users = $this->loadFile('users.php');

        foreach ($users as $user) {
            $groups = $user['groups'] ?? [];
            unset($user['groups']);

            $model = User::withoutTimestamps(fn () => User::updateOrCreate(
                ['id' => $user['id']],
                [
                    'type' => $user['type'],
                    'forename' => $user['forename'],
                    'surname' => $user['surname'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'password' => $user['password'],
                    'config' => $user['config'],
                ],
            ));

            if (! empty($groups)) {
                $syncData = [];
                foreach ($groups as $group) {
                    $syncData[$group['id']] = ['sort' => $group['sort']];
                }
                $model->groups()->sync($syncData);
            }
        }

        $this->command->info('Imported '.count($users).' users.');
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
