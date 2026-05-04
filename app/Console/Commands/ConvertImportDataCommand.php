<?php

namespace App\Console\Commands;

use App\Support\Import\ImportedEmailNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\VarExporter\VarExporter;

class ConvertImportDataCommand extends Command
{
    protected $signature = 'import:convert';

    protected $description = 'Convert ID-based import data files to ID-agnostic format using natural keys';

    private string $importPath;

    /** @var array<int, array<string, mixed>> */
    private array $tagIdMap = [];

    /** @var array<int, string> */
    private array $templateIdToName = [];

    /** @var array<int, string> */
    private array $exerciseIdToName = [];

    /** @var array<int, string> */
    private array $groupIdToName = [];

    public function handle(): int
    {
        $this->importPath = base_path('import/database');

        $tags = $this->loadFile('tags.php');
        $templates = $this->loadFile('exercise_templates.php');
        $exercises = $this->loadFile('exercises.php');
        $programs = $this->loadFile('exercise_programs.php');
        $groups = $this->loadFile('user_groups.php');
        $users = $this->loadFile('users.php');

        $this->buildLookups($tags, $templates, $exercises, $groups);

        $this->writeFile('tags.php', $this->convertTags($tags));
        $this->info('Converted '.count($tags).' tags.');

        $convertedTemplates = $this->convertTemplates($templates);
        $this->writeFile('exercise_templates.php', $convertedTemplates);
        $this->info('Converted '.count($convertedTemplates).' exercise templates.');

        $convertedExercises = $this->convertExercises($exercises);
        $this->writeFile('exercises.php', $convertedExercises);
        $this->info('Converted '.count($convertedExercises).' exercises.');

        $convertedPrograms = $this->convertPrograms($programs);
        $this->writeFile('exercise_programs.php', $convertedPrograms);
        $this->info('Converted '.count($convertedPrograms).' exercise programs.');

        $convertedGroups = $this->convertGroups($groups);
        $this->writeFile('user_groups.php', $convertedGroups);
        $this->info('Converted '.count($convertedGroups).' user groups.');

        $convertedUsers = $this->convertUsers($users);
        $this->writeFile('users.php', $convertedUsers);
        $this->info('Converted '.count($convertedUsers).' users.');

        $this->newLine();
        $this->info('Conversion complete!');

        return 0;
    }

    /** @param array<int, array<string, mixed>> $tags */
    /** @param array<int, array<string, mixed>> $templates */
    /** @param array<int, array<string, mixed>> $exercises */
    /** @param array<int, array<string, mixed>> $groups */
    private function buildLookups(array $tags, array $templates, array $exercises, array $groups): void
    {
        foreach ($tags as $tag) {
            $this->tagIdMap[$tag['id']] = $tag;
        }

        foreach ($templates as $template) {
            $this->templateIdToName[$template['id']] = $template['name'];
        }

        foreach ($exercises as $exercise) {
            $this->exerciseIdToName[$exercise['id']] = $exercise['name'];
        }

        foreach ($groups as $group) {
            $this->groupIdToName[$group['id']] = $group['name'];
        }
    }

    private function tagKey(int $id): string
    {
        $tag = $this->tagIdMap[$id] ?? null;

        if (! $tag) {
            throw new \RuntimeException("Tag ID {$id} not found in lookup.");
        }

        return $tag['scope'].':'.$tag['slug'];
    }

    /** @return array<int, array<string, mixed>> */
    private function convertTags(array $tags): array
    {
        $this->deduplicateTagSlugs($tags);

        $rootTags = [];
        $childrenByParentId = [];

        foreach ($tags as $tag) {
            if ($tag['parent_id'] === null) {
                $rootTags[] = $tag;
            } else {
                $childrenByParentId[$tag['parent_id']][] = $tag;
            }
        }

        $result = [];
        foreach ($rootTags as $tag) {
            $result[] = $this->buildTagNode($tag, $childrenByParentId);
        }

        return $result;
    }

    private function deduplicateTagSlugs(array &$tags): void
    {
        $seen = [];
        foreach ($tags as &$tag) {
            $key = $tag['scope'].':'.$tag['slug'];
            if (isset($seen[$key])) {
                $suffix = 1;
                do {
                    $newSlug = $tag['slug'].'-'.$suffix;
                    $newKey = $tag['scope'].':'.$newSlug;
                    $suffix++;
                } while (isset($seen[$newKey]));

                $this->warn("Duplicate slug '{$key}' found for '{$tag['name']}' (ID {$tag['id']}), renaming to '{$newSlug}'.");
                $tag['slug'] = $newSlug;
                $key = $newKey;
            }
            $seen[$key] = true;
            $this->tagIdMap[$tag['id']] = $tag;
        }
    }

    /** @return array<string, mixed> */
    private function buildTagNode(array $tag, array &$childrenByParentId): array
    {
        $node = [
            'scope' => $tag['scope'],
            'name' => $tag['name'],
            'short_name' => $tag['short_name'],
            'slug' => $tag['slug'],
            'sort_order' => $tag['sort_order'],
        ];

        if (array_key_exists('color', $tag)) {
            $node['color'] = $tag['color'];
        }

        if (array_key_exists('default_exercise_template_id', $tag)) {
            $node['default_exercise_template_id'] = $tag['default_exercise_template_id'];
        }

        if (isset($childrenByParentId[$tag['id']])) {
            $children = [];
            foreach ($childrenByParentId[$tag['id']] as $child) {
                $childNode = $this->buildTagNode($child, $childrenByParentId);
                unset($childNode['scope']);
                $children[] = $childNode;
            }
            $node['children'] = $children;
        }

        return $node;
    }

    private function deduplicateExerciseNames(array &$exercises): void
    {
        $seen = [];
        foreach ($exercises as &$exercise) {
            $normalized = rtrim($exercise['name']);
            if ($normalized !== $exercise['name']) {
                $this->warn("Trimming trailing whitespace from exercise '{$exercise['name']}' (ID {$exercise['id']}).");
                $exercise['name'] = $normalized;
            }
            if (isset($seen[$normalized])) {
                $suffix = 2;
                do {
                    $newName = $normalized.' '.$suffix;
                    $suffix++;
                } while (isset($seen[$newName]));

                $this->warn("Duplicate exercise name '{$normalized}' found (ID {$exercise['id']}), renaming to '{$newName}'.");
                $exercise['name'] = $newName;
                $normalized = $newName;
            }
            $seen[$normalized] = true;
            $this->exerciseIdToName[$exercise['id']] = $exercise['name'];
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function convertTemplates(array $templates): array
    {
        return array_map(function (array $template) {
            $trimmed = rtrim($template['name']);
            if ($trimmed !== $template['name']) {
                $this->warn("Trimming trailing whitespace from template '{$template['name']}' (ID {$template['id']}).");
                $template['name'] = $trimmed;
                $this->templateIdToName[$template['id']] = $trimmed;
            }
            unset($template['id']);

            return $template;
        }, $templates);
    }

    /** @return array<int, array<string, mixed>> */
    private function convertExercises(array $exercises): array
    {
        $this->deduplicateExerciseNames($exercises);

        return array_map(function (array $exercise) {
            $tags = $exercise['tags'] ?? [];

            $converted = [
                'name' => $exercise['name'],
                'category' => $this->tagKey($exercise['category_id']),
                'template' => $exercise['template_id'] !== null
                    ? ($this->templateIdToName[$exercise['template_id']] ?? null)
                    : null,
                'video_url' => $exercise['video_url'],
                'instructions' => $exercise['instructions'],
                'config' => $exercise['config'],
            ];

            if (! empty($tags)) {
                $converted['tags'] = array_map(fn (array $tag) => [
                    'tag' => $this->tagKey($tag['id']),
                    'sort' => $tag['sort'],
                ], $tags);
            } else {
                $converted['tags'] = [];
            }

            return $converted;
        }, $exercises);
    }

    /** @return array<int, array<string, mixed>> */
    private function convertPrograms(array $programs): array
    {
        return array_map(function (array $program) {
            $exercises = $program['exercises'] ?? [];

            $converted = [
                'name' => $program['name'],
                'exercise_category' => $program['exercise_category_id'] !== null
                    ? $this->tagKey($program['exercise_category_id'])
                    : null,
                'sort' => $program['sort'],
            ];

            if (! empty($exercises)) {
                $converted['exercises'] = array_map(fn (array $exercise) => [
                    'exercise' => $this->exerciseIdToName[$exercise['id']],
                    'sort' => $exercise['sort'],
                ], $exercises);
            } else {
                $converted['exercises'] = [];
            }

            return $converted;
        }, $programs);
    }

    /** @return array<int, array<string, mixed>> */
    private function convertGroups(array $groups): array
    {
        return array_map(function (array $group) {
            unset($group['id']);

            return $group;
        }, $groups);
    }

    /** @return array<int, array<string, mixed>> */
    private function convertUsers(array $users): array
    {
        return array_map(function (array $user) {
            $groups = $user['groups'] ?? [];

            $converted = [
                'type' => $user['type'],
                'forename' => $user['forename'],
                'surname' => $user['surname'],
                'email' => ImportedEmailNormalizer::normalize($user['email'] ?? null),
                'phone' => $user['phone'],
                'password' => $user['password'],
                'config' => $user['config'],
            ];

            if (! empty($groups)) {
                $converted['groups'] = array_map(fn (array $group) => [
                    'group' => $this->groupIdToName[$group['id']],
                    'sort' => $group['sort'],
                ], $groups);
            } else {
                $converted['groups'] = [];
            }

            return $converted;
        }, $users);
    }

    /** @return array<int, array<string, mixed>> */
    private function loadFile(string $filename): array
    {
        $path = $this->importPath.'/'.$filename;

        if (! file_exists($path)) {
            $this->warn("File not found: {$path}, skipping.");

            return [];
        }

        return require $path;
    }

    private function writeFile(string $filename, array $data): void
    {
        $content = "<?php\n\nreturn ".VarExporter::export($data).";\n";

        File::put($this->importPath.'/'.$filename, $content);
    }
}
