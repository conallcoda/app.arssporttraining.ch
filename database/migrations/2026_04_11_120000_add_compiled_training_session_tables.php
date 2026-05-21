<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_program_slots', function (Blueprint $table) {
            $table->date('scheduled_date')->nullable()->after('datetime');
            $table->string('status')->default('pending')->after('scheduled_date');
            $table->timestamp('compiled_at')->nullable()->after('status');
            $table->string('compiled_version', 64)->nullable()->after('compiled_at');
            $table->unsignedInteger('exercise_count')->default(0)->after('compiled_version');
            $table->unsignedInteger('completed_exercise_count')->default(0)->after('exercise_count');
            $table->unsignedInteger('partial_exercise_count')->default(0)->after('completed_exercise_count');
            $table->unsignedInteger('skipped_exercise_count')->default(0)->after('partial_exercise_count');
            $table->unsignedInteger('pending_exercise_count')->default(0)->after('skipped_exercise_count');
            $table->boolean('has_any_modification')->default(false)->after('pending_exercise_count');
            $table->timestamp('completed_at')->nullable()->after('has_any_modification');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');

            $table->index(['user_id', 'scheduled_date'], 'training_program_slots_user_date_idx');
            $table->index(['training_program_id', 'scheduled_date'], 'training_program_slots_program_date_idx');
            $table->index(['scheduled_date', 'status'], 'training_program_slots_date_status_idx');
        });

        DB::table('training_program_slots')
            ->select('id', 'datetime')
            ->orderBy('id')
            ->chunkById(200, function ($slots) {
                foreach ($slots as $slot) {
                    DB::table('training_program_slots')
                        ->where('id', $slot->id)
                        ->update([
                            'scheduled_date' => Carbon::parse($slot->datetime)->toDateString(),
                        ]);
                }
            });

        Schema::create('training_program_slot_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_slot_id')
                ->constrained('training_program_slots', indexName: 'tps_exercises_slot_fk')
                ->cascadeOnDelete();
            $table->foreignId('exercise_id')
                ->nullable()
                ->constrained('exercises', indexName: 'tps_exercises_exercise_fk')
                ->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->string('group', 1)->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('set_count')->default(0);
            $table->unsignedInteger('completed_set_count')->default(0);
            $table->unsignedInteger('modified_set_count')->default(0);
            $table->unsignedInteger('skipped_set_count')->default(0);
            $table->unsignedInteger('pending_set_count')->default(0);
            $table->boolean('has_any_modification')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['training_program_slot_id', 'sort'], 'training_program_slot_exercises_slot_sort_idx');
            $table->index(['training_program_slot_id', 'status'], 'training_program_slot_exercises_slot_status_idx');
        });

        Schema::create('training_program_slot_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_slot_exercise_id')
                ->constrained('training_program_slot_exercises', indexName: 'tps_sets_exercise_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('set_number');
            $table->string('status')->default('pending');
            $table->boolean('has_any_modification')->default(false);
            $table->text('athlete_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['training_program_slot_exercise_id', 'set_number'],
                'training_program_slot_sets_exercise_set_unique'
            );
            $table->index(['training_program_slot_exercise_id', 'status'], 'training_program_slot_sets_exercise_status_idx');
        });

        Schema::create('training_program_slot_set_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_slot_set_id')
                ->constrained('training_program_slot_sets', indexName: 'tps_set_values_set_fk')
                ->cascadeOnDelete();
            $table->string('setting_key');
            $table->string('planned_value_type')->nullable();
            $table->bigInteger('planned_int_value')->nullable();
            $table->decimal('planned_decimal_value', 12, 3)->nullable();
            $table->string('planned_string_value')->nullable();
            $table->json('planned_json_value')->nullable();
            $table->string('actual_value_type')->nullable();
            $table->bigInteger('actual_int_value')->nullable();
            $table->decimal('actual_decimal_value', 12, 3)->nullable();
            $table->string('actual_string_value')->nullable();
            $table->json('actual_json_value')->nullable();
            $table->string('unit', 32)->nullable();
            $table->boolean('is_modified')->default(false);
            $table->timestamps();

            $table->unique(
                ['training_program_slot_set_id', 'setting_key'],
                'training_program_slot_set_values_set_setting_unique'
            );
            $table->index(['setting_key'], 'training_program_slot_set_values_setting_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_program_slot_set_values');
        Schema::dropIfExists('training_program_slot_sets');
        Schema::dropIfExists('training_program_slot_exercises');

        Schema::table('training_program_slots', function (Blueprint $table) {
            // Preserve standalone FK-safe indexes before removing the composite date indexes.
            $table->index('user_id', 'training_program_slots_user_id_idx');
            $table->index('training_program_id', 'training_program_slots_program_id_idx');
            $table->dropIndex('training_program_slots_user_date_idx');
            $table->dropIndex('training_program_slots_program_date_idx');
            $table->dropIndex('training_program_slots_date_status_idx');

            $table->dropColumn([
                'scheduled_date',
                'status',
                'compiled_at',
                'compiled_version',
                'exercise_count',
                'completed_exercise_count',
                'partial_exercise_count',
                'skipped_exercise_count',
                'pending_exercise_count',
                'has_any_modification',
                'completed_at',
                'cancelled_at',
            ]);
        });
    }
};
