<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('training_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_season_id')
                ->constrained('training_seasons')
                ->cascadeOnDelete();
            $table->string('name');
            $table->integer('sequence');
            $table->timestamps();

            $table->index('training_season_id');
            $table->unique(['training_season_id', 'sequence']);
        });

        Schema::create('training_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_block_id')
                ->constrained('training_blocks')
                ->cascadeOnDelete();
            $table->integer('sequence');
            $table->timestamps();
            $table->softDeletes();

            $table->index('training_block_id');
            $table->unique(['training_block_id', 'sequence']);
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_week_id')
                ->constrained('training_weeks')
                ->cascadeOnDelete();
            $table->string('name');
            $table->integer('day');
            $table->integer('period');
            $table->timestamps();

            $table->index('training_week_id');
            $table->unique(['training_week_id', 'day', 'period']);
        });

        Schema::create('training_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')
                ->constrained('training_sessions')
                ->cascadeOnDelete();
            $table->integer('sequence');
            $table->integer('reps')->nullable();
            $table->timestamps();

            $table->index('training_session_id');
            $table->unique(['training_session_id', 'sequence']);
        });

        Schema::create('training_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_set_id')
                ->constrained('training_sets')
                ->cascadeOnDelete();
            $table->foreignId('exercise_id')
                ->constrained('exercises')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->index('training_set_id');
            $table->index('exercise_id');
        });

        Schema::create('training_season_athletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_season_id')
                ->constrained('training_seasons')
                ->cascadeOnDelete();
            $table->foreignId('athlete_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_season_id', 'athlete_id'], 'unique_season_athlete');
        });

        Schema::create('training_season_athlete_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_season_id')
                ->constrained('training_seasons')
                ->cascadeOnDelete();
            $table->foreignId('athlete_group_id')
                ->constrained('user_groups')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_season_id', 'athlete_group_id'], 'unique_season_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_season_athlete_groups');
        Schema::dropIfExists('training_season_athletes');
        Schema::dropIfExists('training_exercises');
        Schema::dropIfExists('training_sets');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_weeks');
        Schema::dropIfExists('training_blocks');
        Schema::dropIfExists('training_seasons');
    }
};
