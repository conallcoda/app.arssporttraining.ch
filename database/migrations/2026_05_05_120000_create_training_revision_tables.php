<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_revision_batches', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('domain', 32);
            $table->string('action', 64)->nullable();
            $table->foreignId('changed_by')->nullable();
            $table->string('source', 32)->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('changed_by', 'trb_changed_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['domain', 'created_at'], 'training_revision_batches_domain_created_idx');
        });

        Schema::create('training_actual_value_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id');
            $table->foreignId('training_program_slot_set_value_id');
            $table->foreignId('recorded_by')->nullable();
            $table->string('source', 32)->nullable();
            $table->boolean('was_explicit')->default(false);
            $table->boolean('is_explicit')->default(true);
            $table->boolean('was_modified_from_plan')->default(false);
            $table->boolean('is_modified_from_plan')->default(false);
            $table->string('before_value_type')->nullable();
            $table->bigInteger('before_int_value')->nullable();
            $table->decimal('before_decimal_value', 12, 3)->nullable();
            $table->string('before_string_value')->nullable();
            $table->json('before_json_value')->nullable();
            $table->string('after_value_type')->nullable();
            $table->bigInteger('after_int_value')->nullable();
            $table->decimal('after_decimal_value', 12, 3)->nullable();
            $table->string('after_string_value')->nullable();
            $table->json('after_json_value')->nullable();
            $table->string('unit', 32)->nullable();
            $table->timestamps();

            $table->foreign('batch_id', 'tavr_batch_fk')
                ->references('id')
                ->on('training_revision_batches')
                ->cascadeOnDelete();
            $table->foreign('training_program_slot_set_value_id', 'tavr_slot_set_value_fk')
                ->references('id')
                ->on('training_program_slot_set_values')
                ->cascadeOnDelete();
            $table->foreign('recorded_by', 'tavr_recorded_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(
                ['training_program_slot_set_value_id', 'created_at'],
                'training_actual_value_revisions_value_created_idx'
            );
        });

        Schema::create('training_plan_value_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id');
            $table->morphs('owner');
            $table->foreignId('program_exercise_id');
            $table->foreignId('user_id')->nullable();
            $table->string('setting_key', 64);
            $table->unsignedInteger('week_index');
            $table->unsignedInteger('session_index');
            $table->unsignedInteger('set_index')->nullable();
            $table->foreignId('changed_by')->nullable();
            $table->string('source', 32)->nullable();
            $table->string('before_value_type')->nullable();
            $table->bigInteger('before_int_value')->nullable();
            $table->decimal('before_decimal_value', 12, 3)->nullable();
            $table->string('before_string_value')->nullable();
            $table->json('before_json_value')->nullable();
            $table->string('after_value_type')->nullable();
            $table->bigInteger('after_int_value')->nullable();
            $table->decimal('after_decimal_value', 12, 3)->nullable();
            $table->string('after_string_value')->nullable();
            $table->json('after_json_value')->nullable();
            $table->string('unit', 32)->nullable();
            $table->timestamps();

            $table->foreign('batch_id', 'tpvr_batch_fk')
                ->references('id')
                ->on('training_revision_batches')
                ->cascadeOnDelete();
            $table->foreign('program_exercise_id', 'tpvr_program_exercise_fk')
                ->references('id')
                ->on('exercise_program_exercises')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'tpvr_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('changed_by', 'tpvr_changed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(
                ['owner_type', 'owner_id', 'program_exercise_id', 'setting_key', 'created_at'],
                'training_plan_value_revisions_scope_setting_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_plan_value_revisions');
        Schema::dropIfExists('training_actual_value_revisions');
        Schema::dropIfExists('training_revision_batches');
    }
};
