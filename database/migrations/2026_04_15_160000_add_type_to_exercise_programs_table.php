<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_programs', function (Blueprint $table) {
            $table->string('type')->default('program')->after('name');
            $table->index('type');
        });

        DB::table('exercise_programs')
            ->whereNull('type')
            ->update(['type' => 'program']);
    }

    public function down(): void
    {
        Schema::table('exercise_programs', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
