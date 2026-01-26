<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('exercise_training_plan_program', 'training_plan_program_exercises');

        Schema::table('training_plan_program_exercises', function (Blueprint $table) {
            $table->json('extra')->nullable()->after('sort');
        });
    }

    public function down(): void
    {
        Schema::table('training_plan_program_exercises', function (Blueprint $table) {
            $table->dropColumn('extra');
        });

        Schema::rename('training_plan_program_exercises', 'exercise_training_plan_program');
    }
};
