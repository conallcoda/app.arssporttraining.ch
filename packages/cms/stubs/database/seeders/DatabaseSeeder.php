<?php

namespace Database\Seeders;

use Coda\Cms\Models\Enums\UserTypeEnum;
use Coda\Cms\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'dev@dev.dev'],
            [
                'forename' => 'Dev',
                'surname' => 'User',
                'password' => Hash::make('123456789'),
                'type' => UserTypeEnum::User,
            ],
        );
    }
}
