<?php

namespace Database\Factories;

use App\Models\ExercisePlan;
use App\Models\ExercisePlanProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExercisePlanProgram>
 */
class ExercisePlanProgramFactory extends Factory
{
    protected $model = ExercisePlanProgram::class;

    public function definition(): array
    {
        return [
            'plannable_type' => ExercisePlan::class,
            'plannable_id' => ExercisePlan::factory(),
            'name' => fake()->words(2, true),
        ];
    }
}
