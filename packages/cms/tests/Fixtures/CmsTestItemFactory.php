<?php

namespace Coda\Cms\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class CmsTestItemFactory extends Factory
{
    protected $model = CmsTestItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'status' => 'draft',
            'priority' => 0,
            'is_active' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published']);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => 'archived']);
    }

    public function childOf(CmsTestItem $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }
}
