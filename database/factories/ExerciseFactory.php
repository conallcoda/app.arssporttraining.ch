<?php

namespace Database\Factories;

use App\Models\Exercise\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'config' => ['settings' => [], 'overrides' => ['cells' => [], 'weeks' => []]],
        ];
    }
}
