<?php

namespace Database\Seeders;

use App\Models\TrainingPlan;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Database\Seeder;

class TrainingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plan = TrainingPlan::create(['name' => 'Default Training Plan']);

        $plan->userGroups()->attach(UserGroup::first());
        $plan->users()->attach(User::latest('id')->take(2)->pluck('id'));
    }
}
