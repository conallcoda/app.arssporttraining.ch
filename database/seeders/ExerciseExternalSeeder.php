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
            $model = ExerciseExternal::firstOrCreate(
                ['source' => 'kilo', 'name' => $exercise['short_name']],
                [
                    'video_url' => $exercise['url'] ?? null,
                ],
            );

            $firstCategory = $exercise['categories'][0] ?? null;
            if ($firstCategory) {
                $categoryTag = $this->resolveTag($tagCache, 'exercise_category', $firstCategory);
                $model->update(['category_id' => $categoryTag->id]);
            }

            $tagIds = [];

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
