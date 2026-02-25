<?php

namespace Database\Factories;

use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingPlanProgram>
 */
class TrainingPlanProgramFactory extends Factory
{
    protected $model = TrainingPlanProgram::class;

    public function definition(): array
    {
        return [
            'plannable_type' => TrainingPlan::class,
            'plannable_id' => TrainingPlan::factory(),
            'name' => fake()->words(2, true),
        ];
    }
}
