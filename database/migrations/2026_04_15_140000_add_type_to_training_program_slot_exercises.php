<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_program_slot_exercises', function (Blueprint $table) {
            $table->string('type')->default('main')->after('group');
            $table->index(['training_program_slot_id', 'type', 'sort'], 'tps_exercises_slot_type_sort_idx');
        });

        DB::table('training_program_slot_exercises')
            ->whereNull('type')
            ->update(['type' => 'main']);
    }

    public function down(): void
    {
        Schema::table('training_program_slot_exercises', function (Blueprint $table) {
            // Preserve a leftmost index for the slot FK before removing the composite type index.
            $table->index('training_program_slot_id', 'tps_exercises_slot_id_idx');
            $table->dropIndex('tps_exercises_slot_type_sort_idx');
            $table->dropColumn('type');
        });
    }
};
