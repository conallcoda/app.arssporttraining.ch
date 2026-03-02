<?php

namespace Database\Factories;

use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\TrainingProgram;
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
            'user_id' => null,
            'exercise_program_id' => ExerciseProgram::factory(),
            'source_plan_id' => null,
            'sort' => 0,
        ];
    }
}
