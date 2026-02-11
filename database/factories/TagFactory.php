<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'scope' => fake()->randomElement(['muscle_group', 'equipment', 'movement_pattern', 'difficulty']),
            'name' => fake()->unique()->words(2, true),
            'short_name' => null,
        ];
    }

    public function withScope(string $scope): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => $scope,
        ]);
    }
}
