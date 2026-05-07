<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_state_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id');
            $table->morphs('subject');
            $table->string('state_key', 32);
            $table->string('before_value', 64)->nullable();
            $table->string('after_value', 64)->nullable();
            $table->json('before_payload')->nullable();
            $table->json('after_payload')->nullable();
            $table->foreignId('changed_by')->nullable();
            $table->string('source', 32)->nullable();
            $table->timestamps();

            $table->foreign('batch_id', 'tsr_batch_fk')
                ->references('id')
                ->on('training_revision_batches')
                ->cascadeOnDelete();
            $table->foreign('changed_by', 'tsr_changed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(
                ['subject_type', 'subject_id', 'state_key', 'created_at'],
                'training_state_revisions_subject_state_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_state_revisions');
    }
};
