<?php

namespace Database\Factories;

use App\Data\Athlete\Metric\MetricEnum;
use App\Models\Athlete\MetricSubmission;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetricSubmission>
 */
class MetricSubmissionFactory extends Factory
{
    protected $model = MetricSubmission::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->athlete(),
            'metric' => MetricEnum::OneRepMax,
            'recorded_by' => User::factory()->coach(),
            'recorded_at' => fake()->date(),
        ];
    }

    public function oneRepMax(): static
    {
        return $this->state(fn (array $attributes) => [
            'metric' => MetricEnum::OneRepMax,
        ]);
    }

}
