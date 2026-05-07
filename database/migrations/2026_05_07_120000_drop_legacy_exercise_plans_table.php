<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('exercise_plan_config_overrides')
            ->where('owner_type', 'App\\Models\\Exercise\\ExercisePlan')
            ->delete();

        Schema::dropIfExists('exercise_plans');
    }

    public function down(): void
    {
        Schema::create('exercise_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
