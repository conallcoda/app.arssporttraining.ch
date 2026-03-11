<?php

namespace App\Console\Commands;

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
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

    public function handle(): int
    {
        $this->exportPath = base_path('import/database');

        File::ensureDirectoryExists($this->exportPath);

        $this->buildLookups();

        $this->exportTags();
        $this->exportExerciseTemplates();
        $this->exportExercises();
        $this->exportExercisePrograms();
        $this->exportUserGroups();
        $this->exportUsers();

        $this->newLine();
        $this->info('Export complete!');

        return 0;
    }

    private function buildLookups(): void
    {
        Tag::all()->each(function (Tag $tag) {
            $this->tagIdMap[$tag->id] = ['scope' => $tag->scope, 'slug' => $tag->slug];
        });

        ExerciseTemplate::all()->each(function (ExerciseTemplate $template) {
            $this->templateIdToName[$template->id] = $template->name;
        });

        Exercise::all()->each(function (Exercise $exercise) {
            $this->exerciseIdToName[$exercise->id] = $exercise->name;
        });

        UserGroup::all()->each(function (UserGroup $group) {
            $this->groupIdToName[$group->id] = $group->name;
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
            ->map(fn (ExerciseProgram $program) => [
                'name' => $program->name,
                'exercise_category' => $program->exercise_category_id !== null
                    ? $this->tagKey($program->exercise_category_id)
                    : null,
                'sort' => $program->sort,
                'exercises' => $program->exercises->map(fn (Exercise $exercise) => [
                    'exercise' => $exercise->name,
                    'sort' => $exercise->pivot->sort,
                ])->all(),
            ])
            ->all();

        $this->writeFile('exercise_programs.php', $programs);
        $this->info('Exported '.count($programs).' exercise programs.');
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

    private function writeFile(string $filename, array $data): void
    {
        $content = "<?php\n\nreturn ".VarExporter::export($data).";\n";

        File::put($this->exportPath.'/'.$filename, $content);
    }
}
