<?php

namespace Database\Seeders;

use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Models\Users\UserTypeEnum;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/athletes.php');

        $usersByName = collect();

        foreach ($data['users'] as $userData) {
            $type = UserTypeEnum::from($userData['type']);

            $user = User::factory()->state([
                'forename' => $userData['forename'],
                'surname' => $userData['surname'],
                'type' => $type,
            ])->create();

            $usersByName->put("{$userData['forename']} {$userData['surname']}", $user);
        }

        foreach ($data['groups'] as $groupName => $members) {
            $group = UserGroup::create(['name' => $groupName]);

            $memberIds = collect($members)
                ->values()
                ->mapWithKeys(fn (string $name, int $index) => [
                    $usersByName->get($name)->id => ['sort' => $index],
                ]);

            $group->members()->attach($memberIds);
        }
    }
}
