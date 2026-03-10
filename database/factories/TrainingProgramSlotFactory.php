<?php

namespace Database\Factories;

use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingProgramSlot>
 */
class TrainingProgramSlotFactory extends Factory
{
    protected $model = TrainingProgramSlot::class;

    public function definition(): array
    {
        return [
            'training_program_id' => TrainingProgram::factory(),
            'user_id' => User::factory(),
            'datetime' => fake()->dateTime(),
        ];
    }
}
