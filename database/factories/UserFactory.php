<?php

namespace Database\Factories;

use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
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
            'forename' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'type' => fake()->randomElement(UserTypeEnum::cases()),
            'phone' => null,
            'password' => static::$password ??= Hash::make('123456789'),
            'remember_token' => Str::random(10),
        ];
    }

    public function coach(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserTypeEnum::Coach,
        ]);
    }

    public function athlete(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserTypeEnum::Athlete,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserTypeEnum::Admin,
        ]);
    }
}
