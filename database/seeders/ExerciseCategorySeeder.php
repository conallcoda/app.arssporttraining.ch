<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class ExerciseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = require database_path('seeders/data/exercise_categories.php');

        $this->seedTree($categories);
    }

    protected function seedTree(array $nodes, ?Tag $parent = null): void
    {
        foreach ($nodes as $key => $value) {
            if (is_string($key)) {
                $tag = Tag::updateOrCreate(
                    ['name' => $key, 'scope' => 'exercise_category'],
                    ['parent_id' => $parent?->id],
                );

                if (is_array($value)) {
                    $this->seedTree($value, $tag);
                }
            } else {
                Tag::updateOrCreate(
                    ['name' => $value, 'scope' => 'exercise_category'],
                    ['parent_id' => $parent?->id],
                );
            }
        }
    }
}
