<?php

namespace Database\Factories;

use App\Models\ExerciseProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseProgram>
 */
class ExerciseProgramFactory extends Factory
{
    protected $model = ExerciseProgram::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'program_category_id' => null,
        ];
    }
}
