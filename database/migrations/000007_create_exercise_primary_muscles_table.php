<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_primary_muscles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->string('exercise_muscle_id');
            $table->timestamps();

            $table->foreign('exercise_muscle_id')->references('id')->on('exercise_muscles')->cascadeOnDelete();
            $table->unique(['exercise_id', 'exercise_muscle_id'], 'exercise_primary_muscle_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_primary_muscles');
    }
};
