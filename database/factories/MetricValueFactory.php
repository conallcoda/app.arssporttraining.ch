<?php

namespace Database\Factories;

use App\Models\Athlete\MetricSubmission;
use App\Models\Athlete\MetricValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetricValue>
 */
class MetricValueFactory extends Factory
{
    protected $model = MetricValue::class;

    public function definition(): array
    {
        return [
            'submission_id' => MetricSubmission::factory(),
            'field' => fake()->word(),
            'value' => (string) fake()->randomFloat(1, 1, 200),
        ];
    }
}
