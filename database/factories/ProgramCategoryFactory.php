<?php

namespace Database\Factories;

use App\Models\ProgramCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramCategory>
 */
class ProgramCategoryFactory extends Factory
{
    protected $model = ProgramCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'color' => fake()->randomElement(['blue', 'green', 'red', 'purple', 'orange', 'teal']),
        ];
    }
}
