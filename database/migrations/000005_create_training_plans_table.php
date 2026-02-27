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
        Schema::create('training_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('training_plan_user_group', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('training_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_plan_id', 'user_group_id']);
        });

        Schema::create('training_plan_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('training_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_plan_id', 'user_id']);
        });

        Schema::create('training_plan_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('training_plan_program_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['training_plan_program_id', 'exercise_id'], 'program_exercise_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_plan_program_exercises');
        Schema::dropIfExists('training_plan_programs');
        Schema::dropIfExists('training_plan_user');
        Schema::dropIfExists('training_plan_user_group');
        Schema::dropIfExists('training_plans');
    }
};
