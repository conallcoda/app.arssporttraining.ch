<?php

namespace Database\Factories;

use App\Models\Exercise\ExerciseProgramCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseProgramCategory>
 */
class ExerciseProgramCategoryFactory extends Factory
{
    protected $model = ExerciseProgramCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'color' => fake()->randomElement(['blue', 'green', 'red', 'purple', 'orange', 'teal']),
        ];
    }
}
