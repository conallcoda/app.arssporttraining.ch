<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('exercise_plans')->get()->each(function ($plan) {
            $config = json_decode($plan->config, true);
            if (! $config || ! isset($config['schedule']['weeks'])) {
                return;
            }

            foreach ($config['schedule']['weeks'] as &$week) {
                if (! isset($week['slots'])) {
                    continue;
                }
                foreach ($week['slots'] as &$slot) {
                    $slot['programs'] = [];
                }
            }

            DB::table('exercise_plans')
                ->where('id', $plan->id)
                ->update(['config' => json_encode($config)]);
        });

        Schema::dropIfExists('exercise_plan_program_exercises');
        Schema::dropIfExists('exercise_plan_programs');
    }

    public function down(): void
    {
        //
    }
};
