<?php

namespace Database\Factories;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['admin', 'coach', 'athlete']),
            'forename' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'password' => static::$password ??= Hash::make('123456789'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'admin',
        ]);
    }

    public function coach(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'coach',
        ]);
    }

    public function athlete(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'athlete',
        ]);
    }
}
