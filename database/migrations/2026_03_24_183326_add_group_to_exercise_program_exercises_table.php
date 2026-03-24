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
        Schema::table('exercise_program_exercises', function (Blueprint $table) {
            $table->char('group', 1)->nullable()->after('sort');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_program_exercises', function (Blueprint $table) {
            $table->dropColumn('group');
        });
    }
};
