<?php

namespace Database\Seeders;

use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $class11 = UserGroup::create(['name' => 'Class 11']);
        $class12 = UserGroup::create(['name' => 'Class 12']);
        $class13 = UserGroup::create(['name' => 'Class 13']);

        User::factory()->admin()->create([
            'forename' => 'Admin',
            'surname' => 'User',
            'email' => 'dev@dev.dev',
        ]);

        User::factory()->coach()->count(3)->create();

        $weights = [52, 55, 57, 60, 102, 105, 107.5, 115, 122.5, 52, 55, 57, 60, 102, 105, 107.5, 115, 122.5, 52, 55, 57, 60, 102, 105, 107.5, 115, 122.5, 52, 55, 57, 60, 102, 105, 107.5, 115, 122.5];

        $athletes = collect();
        foreach ($weights as $weight) {
            $athletes->push(User::factory()->athlete()->create([
                'config' => [
                    'test_reps' => 1,
                    'test_weight' => $weight,
                    'target_modifier' => 100.0,
                ],
            ]));
        }

        $class11->members()->attach(
            $athletes->slice(0, 3)->values()->mapWithKeys(fn ($user, $index) => [$user->id => ['sort' => $index]])
        );
        $class12->members()->attach(
            $athletes->slice(3, 10)->values()->mapWithKeys(fn ($user, $index) => [$user->id => ['sort' => $index]])
        );
        $class13->members()->attach(
            $athletes->slice(13, 6)->values()->mapWithKeys(fn ($user, $index) => [$user->id => ['sort' => $index]])
        );
    }
}
