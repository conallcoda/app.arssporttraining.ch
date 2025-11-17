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
            $table->string('scope');
            $table->string('type');
            $table->string('name');
            $table->string('label');
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['scope', 'type', 'name']);
        });

        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_type_id')->constrained('metric_types')->cascadeOnDelete();
            $table->morphs('metricable');
            $table->json('value');
            $table->json('extra')->nullable();
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
