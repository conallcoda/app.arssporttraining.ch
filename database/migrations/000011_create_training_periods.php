<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('type');
            $table->string('name')->nullable();
            $table->integer('sequence')->default(0);
            $table->schemalessAttributes('extra');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('parent_id')->references('id')->on('training_periods')->cascadeOnDelete();
        });

        Schema::create('training_session_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('text_color')->nullable();
            $table->string('background_color')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_session_categories');
        Schema::dropIfExists('training_periods');
    }
};
