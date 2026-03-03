<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('exercise_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('config');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exercises_external', function (Blueprint $table) {
            $table->id();
            $table->string('source')->index();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->string('video_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exercise_program_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exercise_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->foreignId('external_id')->nullable()->constrained('exercises_external')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('exercise_templates')->nullOnDelete();
            $table->string('video_url')->nullable();
            $table->text('instructions')->nullable();
            $table->json('config');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exercise_programs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->foreignId('program_category_id')->nullable()->constrained('exercise_program_categories')->nullOnDelete();
            $table->integer('sort')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exercise_program_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_program_id')->constrained('exercise_programs')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('exercise_plan_programs', function (Blueprint $table) {
            $table->id();
            $table->string('plannable_type');
            $table->unsignedBigInteger('plannable_id');
            $table->foreignId('program_category_id')->nullable()->constrained('exercise_program_categories')->nullOnDelete();
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
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();
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
        Schema::dropIfExists('exercise_program_exercises');
        Schema::dropIfExists('exercise_programs');
        Schema::dropIfExists('exercises');
        Schema::dropIfExists('exercise_plans');
        Schema::dropIfExists('exercise_program_categories');
        Schema::dropIfExists('exercises_external');
        Schema::dropIfExists('exercise_templates');
    }
};
