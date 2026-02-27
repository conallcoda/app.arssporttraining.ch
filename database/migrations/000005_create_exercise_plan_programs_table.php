<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_plan_programs', function (Blueprint $table) {
            $table->id();
            $table->string('plannable_type');
            $table->unsignedBigInteger('plannable_id');
            $table->foreignId('program_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['plannable_type', 'plannable_id']);
        });

        Schema::create('exercise_plan_program_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_plan_program_id')->constrained('exercise_plan_programs')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['exercise_plan_program_id', 'exercise_id'], 'program_exercise_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_plan_program_exercises');
        Schema::dropIfExists('exercise_plan_programs');
    }
};
