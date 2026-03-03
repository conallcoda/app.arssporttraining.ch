<?php

namespace Database\Factories;

use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
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
            'datetime' => fake()->dateTime(),
            'active' => true,
            'config' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
