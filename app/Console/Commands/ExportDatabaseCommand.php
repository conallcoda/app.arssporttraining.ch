<?php

namespace App\Console\Commands;

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgramCategory;
use App\Models\Exercise\ExerciseTemplate;
use App\Models\Tag;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\VarExporter\VarExporter;

class ExportDatabaseCommand extends Command
{
    protected $signature = 'db:export';

    protected $description = 'Export tags, exercises, exercise templates, and program categories to PHP files';

    private string $exportPath;

    public function handle(): int
    {
        $this->exportPath = base_path('import/database');

        File::ensureDirectoryExists($this->exportPath);

        $this->exportTags();
        $this->exportExerciseTemplates();
        $this->exportExercises();
        $this->exportProgramCategories();
        $this->exportUserGroups();
        $this->exportUsers();

        $this->newLine();
        $this->info('Export complete!');

        return 0;
    }

    private function exportTags(): void
    {
        $allTags = Tag::query()
            ->orderBy('scope')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $sorted = collect();
        $remaining = $allTags->keyBy('id');

        while ($remaining->isNotEmpty()) {
            $batch = $remaining->filter(fn (Tag $tag) => $tag->parent_id === null || $sorted->has($tag->parent_id));

            if ($batch->isEmpty()) {
                $sorted = $sorted->merge($remaining);
                break;
            }

            $sorted = $sorted->merge($batch);
            $remaining = $remaining->diffKeys($batch);
        }

        $tags = $sorted->values()
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'scope' => $tag->scope,
                'name' => $tag->name,
                'short_name' => $tag->short_name,
                'slug' => $tag->slug,
                'parent_id' => $tag->parent_id,
                'sort_order' => $tag->sort_order,
            ])
            ->all();

        $this->writeFile('tags.php', $tags);
        $this->info('Exported '.count($tags).' tags.');
    }

    private function exportExerciseTemplates(): void
    {
        $templates = ExerciseTemplate::query()
            ->orderBy('name')
            ->get()
            ->map(fn (ExerciseTemplate $template) => [
                'id' => $template->id,
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
                'id' => $exercise->id,
                'name' => $exercise->name,
                'category_id' => $exercise->category_id,
                'template_id' => $exercise->template_id,
                'video_url' => $exercise->video_url,
                'instructions' => $exercise->instructions,
                'config' => json_decode($exercise->getRawOriginal('config'), true),
                'tags' => $exercise->tags->map(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'sort' => $tag->pivot->sort,
                ])->all(),
            ])
            ->all();

        $this->writeFile('exercises.php', $exercises);
        $this->info('Exported '.count($exercises).' exercises.');
    }

    private function exportProgramCategories(): void
    {
        $categories = ExerciseProgramCategory::query()
            ->orderBy('sort')
            ->get()
            ->map(fn (ExerciseProgramCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'sort' => $category->sort,
                'config' => json_decode($category->getRawOriginal('config'), true),
            ])
            ->all();

        $this->writeFile('program_categories.php', $categories);
        $this->info('Exported '.count($categories).' program categories.');
    }

    private function exportUserGroups(): void
    {
        $groups = UserGroup::query()
            ->orderBy('name')
            ->get()
            ->map(fn (UserGroup $group) => [
                'id' => $group->id,
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
                'id' => $user->id,
                'type' => $user->getRawOriginal('type'),
                'forename' => $user->forename,
                'surname' => $user->surname,
                'email' => $user->email,
                'phone' => $user->phone,
                'password' => $user->getRawOriginal('password'),
                'config' => json_decode($user->getRawOriginal('config'), true),
                'groups' => $user->groups->map(fn (UserGroup $group) => [
                    'id' => $group->id,
                    'sort' => $group->pivot->sort,
                ])->all(),
            ])
            ->all();

        $this->writeFile('users.php', $users);
        $this->info('Exported '.count($users).' users.');
    }

    private function writeFile(string $filename, array $data): void
    {
        $content = "<?php\n\nreturn ".VarExporter::export($data).";\n";

        File::put($this->exportPath.'/'.$filename, $content);
    }
}
