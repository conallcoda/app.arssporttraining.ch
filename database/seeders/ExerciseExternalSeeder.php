<?php

namespace Database\Seeders;

use App\Models\Exercise\ExerciseExternal;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class ExerciseExternalSeeder extends Seeder
{
    public function run(): void
    {
        $data = require base_path('import/kilo/classified_exercises.php');

        $tagCache = [];

        foreach ($data['exercises'] as $exercise) {
            $template = match ($exercise['type']) {
                'strength_manual', 'strength_automatic' => 'weight',
                'conditioning' => 'conditioning',
                'timed' => 'timed',
            };

            $model = ExerciseExternal::firstOrCreate(
                ['source' => 'kilo', 'name' => $exercise['full_name']],
                [
                    'short_name' => $exercise['short_name'],
                    'template' => $template,
                    'video_url' => $exercise['url'] ?? null,
                ],
            );

            $tagIds = [];

            foreach ($exercise['categories'] as $sort => $category) {
                $tag = $this->resolveTag($tagCache, 'exercise_category', $category);
                $tagIds[$tag->id] = ['sort' => $sort];
            }

            foreach ($exercise['equipment'] as $sort => $item) {
                $tag = $this->resolveTag($tagCache, 'exercise_equipment', $item);
                $tagIds[$tag->id] = ['sort' => $sort];
            }

            foreach ($exercise['variants'] as $sort => $variant) {
                $tag = $this->resolveTag($tagCache, 'exercise_modifiers', $variant);
                $tagIds[$tag->id] = ['sort' => $sort];
            }

            $model->tags()->syncWithoutDetaching($tagIds);
        }
    }

    protected function resolveTag(array &$cache, string $scope, string $name): Tag
    {
        $key = "{$scope}:{$name}";

        if (! isset($cache[$key])) {
            $cache[$key] = Tag::firstOrCreate(
                ['name' => $name, 'scope' => $scope],
            );
        }

        return $cache[$key];
    }
}
