<?php

namespace Database\Factories;

use App\Models\Exercise\ExerciseExternal;
use App\Models\Exercise\ExerciseExternalTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseExternal>
 */
class ExerciseExternalFactory extends Factory
{
    protected $model = ExerciseExternal::class;

    public function definition(): array
    {
        return [
            'source' => 'kilo',
            'name' => fake()->unique()->words(3, true),
            'short_name' => fake()->word(),
            'template' => fake()->randomElement(ExerciseExternalTemplate::cases()),
            'video_url' => fake()->optional()->url(),
        ];
    }
}
