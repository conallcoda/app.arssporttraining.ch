<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropForeign(['source_plan_id']);
            $table->dropColumn('source_plan_id');
        });

        DB::table('training_program_slots')->truncate();

        Schema::table('training_program_slots', function (Blueprint $table) {
            $table->foreignId('user_id')->after('training_program_id')->constrained('users')->cascadeOnDelete();
            $table->dropColumn('active');
            $table->dropColumn('config');
            $table->dropSoftDeletes();

            $table->unique(['training_program_id', 'user_id', 'datetime'], 'training_program_slots_unique');
        });
    }

    public function down(): void
    {
        Schema::table('training_program_slots', function (Blueprint $table) {
            $table->dropUnique('training_program_slots_unique');
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->boolean('active')->default(true)->after('datetime');
            $table->json('config')->nullable()->after('active');
            $table->softDeletes();
        });

        Schema::table('training_programs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('group_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_plan_id')->nullable()->after('exercise_program_id')->constrained('exercise_plans')->nullOnDelete();
        });
    }
};
