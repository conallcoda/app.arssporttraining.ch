<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_exercise_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->foreignId('exercise_equipment_id')->constrained('exercise_equipment')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['exercise_id', 'exercise_equipment_id'], 'exercise_equipment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_exercise_equipment');
    }
};
