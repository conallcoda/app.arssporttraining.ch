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
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->foreignId('exercise_program_id')->constrained('exercise_programs')->cascadeOnDelete();
            $table->integer('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('training_program_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained('training_programs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('datetime');
            $table->timestamps();

            $table->unique(['training_program_id', 'user_id', 'datetime'], 'training_program_slots_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_program_slots');
        Schema::dropIfExists('training_programs');
    }
};
