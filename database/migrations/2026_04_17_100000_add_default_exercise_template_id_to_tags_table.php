<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->foreignId('default_exercise_template_id')
                ->nullable()
                ->after('color')
                ->constrained('exercise_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_exercise_template_id');
        });
    }
};
