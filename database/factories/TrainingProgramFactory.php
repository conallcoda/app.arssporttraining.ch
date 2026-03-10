<?php

namespace Database\Factories;

use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingProgram>
 */
class TrainingProgramFactory extends Factory
{
    protected $model = TrainingProgram::class;

    public function definition(): array
    {
        return [
            'group_id' => 1,
            'exercise_program_id' => ExerciseProgram::factory(),
            'sort' => 0,
        ];
    }
}
