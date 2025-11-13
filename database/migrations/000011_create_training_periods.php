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
            $table->string('type'); // Required for Parental (single-table inheritance)
            $table->string('name')->nullable();
            $table->integer('sequence')->default(0);
            $table->schemalessAttributes('extra');
            $table->nestedSet();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_periods');
    }
};
