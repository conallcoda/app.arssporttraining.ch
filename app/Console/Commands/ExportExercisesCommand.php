<?php

namespace App\Console\Commands;

use App\Models\Exercise\Exercise;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\VarExporter\VarExporter;

class ExportExercisesCommand extends Command
{
    protected $signature = 'exercises:export {exercise? : Specific exercise ID to export}';

    protected $description = 'Export exercise categories, tags, and exercises to import/database files, appending only new items';

    private string $exportPath;

    private array $stats = [
        'tags' => ['new' => 0, 'existing' => 0],
        'exercises' => ['new' => 0, 'existing' => 0],
    ];

    public function handle(): int
    {
        $this->exportPath = base_path('import/database');
        $exerciseId = $this->argument('exercise');

        File::ensureDirectoryExists($this->exportPath);

        $exercises = $this->loadExercises($exerciseId);

        if ($exercises->isEmpty()) {
            $this->error($exerciseId
                ? "Exercise with ID {$exerciseId} not found."
                : 'No exercises found in the database.');

            return 1;
        }

        $this->info($exerciseId
            ? "Exporting exercise ID {$exerciseId} and its related data..."
            : 'Exporting all exercises and related data...');

        $tagIds = $this->collectTagIds($exercises);
        $tags = Tag::query()->whereIn('id', $tagIds)->get();
        $tags = $this->includeParentTags($tags);

        $this->exportTags($tags);
        $this->exportExercises($exercises);

        $this->newLine();
        $this->info('Export complete!');
        $this->table(
            ['Type', 'New', 'Already Existed'],
            [
                ['Tags', $this->stats['tags']['new'], $this->stats['tags']['existing']],
                ['Exercises', $this->stats['exercises']['new'], $this->stats['exercises']['existing']],
            ]
        );

        return 0;
    }

    private function loadExercises(?string $exerciseId): Collection
    {
        $query = Exercise::query()->with('tags');

        if ($exerciseId) {
            $query->where('id', $exerciseId);
        }

        return $query->orderBy('name')->get();
    }

    private function collectTagIds(Collection $exercises): array
    {
        $tagIds = [];

        foreach ($exercises as $exercise) {
            if ($exercise->category_id) {
                $tagIds[] = $exercise->category_id;
            }

            foreach ($exercise->tags as $tag) {
                $tagIds[] = $tag->id;
            }
        }

        return array_unique($tagIds);
    }

    private function includeParentTags(Collection $tags): Collection
    {
        $allTags = $tags->keyBy('id');
        $parentIds = $tags->pluck('parent_id')->filter()->unique();

        while ($parentIds->isNotEmpty()) {
            $missingParentIds = $parentIds->diff($allTags->keys());

            if ($missingParentIds->isEmpty()) {
                break;
            }

            $parents = Tag::query()->whereIn('id', $missingParentIds)->get();
            foreach ($parents as $parent) {
                $allTags[$parent->id] = $parent;
            }

            $parentIds = $parents->pluck('parent_id')->filter()->unique();
        }

        return $allTags->values();
    }

    private function exportTags(Collection $tags): void
    {
        $existingTags = $this->loadExistingFile('tags.php');
        $existingIds = collect($existingTags)->pluck('id')->all();

        $newTags = [];

        $sorted = $this->topologicalSortTags($tags);

        foreach ($sorted as $tag) {
            if (in_array($tag->id, $existingIds)) {
                $this->stats['tags']['existing']++;

                continue;
            }

            $newTags[] = [
                'id' => $tag->id,
                'scope' => $tag->scope,
                'name' => $tag->name,
                'short_name' => $tag->short_name,
                'slug' => $tag->slug,
                'parent_id' => $tag->parent_id,
                'sort_order' => $tag->sort_order,
            ];

            $this->stats['tags']['new']++;
        }

        if (! empty($newTags)) {
            $merged = array_merge($existingTags, $newTags);
            $this->writeFile('tags.php', $merged);
            $this->info('Appended '.count($newTags).' new tags.');
        } else {
            $this->info('No new tags to export.');
        }
    }

    /** @return Tag[] */
    private function topologicalSortTags(Collection $tags): array
    {
        $sorted = collect();
        $remaining = $tags->keyBy('id');

        while ($remaining->isNotEmpty()) {
            $batch = $remaining->filter(fn (Tag $tag) => $tag->parent_id === null || $sorted->has($tag->parent_id) || ! $remaining->has($tag->parent_id));

            if ($batch->isEmpty()) {
                $sorted = $sorted->merge($remaining);
                break;
            }

            $sorted = $sorted->merge($batch);
            $remaining = $remaining->diffKeys($batch);
        }

        return $sorted->values()->all();
    }

    private function exportExercises(Collection $exercises): void
    {
        $existingExercises = $this->loadExistingFile('exercises.php');
        $existingIds = collect($existingExercises)->pluck('id')->all();

        $newExercises = [];

        foreach ($exercises as $exercise) {
            if (in_array($exercise->id, $existingIds)) {
                $this->stats['exercises']['existing']++;

                continue;
            }

            $newExercises[] = [
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
            ];

            $this->stats['exercises']['new']++;
        }

        if (! empty($newExercises)) {
            $merged = array_merge($existingExercises, $newExercises);
            $this->writeFile('exercises.php', $merged);
            $this->info('Appended '.count($newExercises).' new exercises.');
        } else {
            $this->info('No new exercises to export.');
        }
    }

    private function loadExistingFile(string $filename): array
    {
        $path = $this->exportPath.'/'.$filename;

        if (! File::exists($path)) {
            return [];
        }

        return require $path;
    }

    private function writeFile(string $filename, array $data): void
    {
        $content = "<?php\n\nreturn ".VarExporter::export($data).";\n";

        File::put($this->exportPath.'/'.$filename, $content);
    }
}
