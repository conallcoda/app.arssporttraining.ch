<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('exercise_program_id')->constrained('exercise_programs')->cascadeOnDelete();
            $table->foreignId('source_plan_id')->nullable()->constrained('exercise_plans')->nullOnDelete();
            $table->integer('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('training_program_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained('training_programs')->cascadeOnDelete();
            $table->dateTime('datetime');
            $table->boolean('active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_program_slots');
        Schema::dropIfExists('training_programs');
    }
};
