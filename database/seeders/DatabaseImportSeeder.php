<?php

namespace Database\Seeders;

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseTemplate;
use App\Models\Tag;
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

    public function run(): void
    {
        $this->importPath = base_path('import/database');

        $this->seedTags();
        $this->seedExerciseTemplates();
        $this->seedExercises();
        $this->seedExercisePrograms();
        $this->seedUserGroups();
        $this->seedUsers();
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

    private function seedExercisePrograms(): void
    {
        $programs = $this->loadFile('exercise_programs.php');

        foreach ($programs as $program) {
            $exercises = $program['exercises'] ?? [];

            $model = ExerciseProgram::updateOrCreate(
                ['name' => $program['name']],
                [
                    'exercise_category_id' => $program['exercise_category'] !== null
                        ? $this->resolveTagKey($program['exercise_category'])
                        : null,
                    'sort' => $program['sort'],
                ],
            );

            if (! empty($exercises)) {
                $syncData = [];
                foreach ($exercises as $exercise) {
                    $syncData[$this->resolveExerciseName($exercise['exercise'])] = ['sort' => $exercise['sort']];
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
        }

        $this->command->info('Imported '.count($users).' users.');
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
