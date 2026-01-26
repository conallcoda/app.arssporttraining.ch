<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('training_plan_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exercise_training_plan_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['training_plan_program_id', 'exercise_id'], 'program_exercise_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_training_plan_program');
        Schema::dropIfExists('training_plan_programs');
    }
};
