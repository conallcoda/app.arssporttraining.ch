<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('metric_types', function (Blueprint $table) {
            $table->id();
            $table->string('model_base');
            $table->string('model_sub');
            $table->string('type');
            $table->string('name');
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index for efficient lookups
            $table->index(['model_base', 'model_sub', 'type']);
        });

        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_type_id')->constrained('metric_types')->cascadeOnDelete();
            $table->morphs('metricable');
            $table->json('value');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics');
        Schema::dropIfExists('metric_types');
    }
};
