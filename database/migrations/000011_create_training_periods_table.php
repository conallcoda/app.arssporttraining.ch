<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        return;
        Schema::create('training_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->uuid('linked_to')->nullable();
            $table->string('type');
            $table->string('name')->nullable();
            $table->integer('sequence')->default(0);
            $table->schemalessAttributes('extra');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('parent_id')->references('id')->on('training_periods')->cascadeOnDelete();
        });

        Schema::table('training_periods', function (Blueprint $table) {
            $table->foreign('linked_to')->references('uuid')->on('training_periods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_periods');
    }
};
