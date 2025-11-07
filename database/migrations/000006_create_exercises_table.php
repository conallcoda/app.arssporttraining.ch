<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('force')->nullable();
            $table->string('level')->nullable();
            $table->string('mechanic')->nullable();
            $table->string('exercise_equipment_id')->nullable();
            $table->string('exercise_category_id')->nullable();
            $table->json('instructions')->nullable();
            $table->schemalessAttributes('extra');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('exercise_equipment_id')->references('id')->on('exercise_equipment')->nullOnDelete();
            $table->foreign('exercise_category_id')->references('id')->on('exercise_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
