<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_programs', function (Blueprint $table) {
            $table->nullableMorphs('owner');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_programs', function (Blueprint $table) {
            $table->dropMorphs('owner');
        });
    }
};
