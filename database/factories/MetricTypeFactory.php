<?php

namespace Database\Factories;

use App\Models\Metrics\MetricType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetricType>
 */
class MetricTypeFactory extends Factory
{
    protected $model = MetricType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
        ];
    }
}
