<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_programs', function (Blueprint $table): void {
            $table->unsignedInteger('planned_session_count')->nullable()->after('status');
        });

        Schema::table('training_program_slots', function (Blueprint $table): void {
            $table->unsignedInteger('session_index')->nullable()->after('scheduled_date');
            $table->index(
                ['training_program_id', 'user_id', 'session_index'],
                'training_program_slots_program_user_session_idx',
            );
        });

        $nextIndexes = [];

        DB::table('training_program_slots')
            ->select(['id', 'training_program_id', 'user_id'])
            ->orderBy('training_program_id')
            ->orderBy('user_id')
            ->orderBy('datetime')
            ->orderBy('id')
            ->chunk(500, function ($slots) use (&$nextIndexes): void {
                foreach ($slots as $slot) {
                    $key = $slot->training_program_id.':'.$slot->user_id;
                    $sessionIndex = $nextIndexes[$key] ?? 0;

                    DB::table('training_program_slots')
                        ->where('id', $slot->id)
                        ->update(['session_index' => $sessionIndex]);

                    $nextIndexes[$key] = $sessionIndex + 1;
                }
            });

        // planned_session_count is an explicit planner override, not historical
        // data. Existing programs continue to infer their effective count from
        // the slots in the currently selected planning scope.
    }

    public function down(): void
    {
        DB::table('training_program_blocks')
            ->whereNotNull('config')
            ->select(['id', 'config'])
            ->orderBy('id')
            ->chunkById(500, function ($blocks): void {
                foreach ($blocks as $block) {
                    $config = json_decode((string) $block->config, true);
                    if (! is_array($config) || ! array_key_exists('plannedSessionCounts', $config)) {
                        continue;
                    }

                    unset($config['plannedSessionCounts']);

                    DB::table('training_program_blocks')
                        ->where('id', $block->id)
                        ->update(['config' => json_encode($config)]);
                }
            });

        Schema::table('training_program_slots', function (Blueprint $table): void {
            $table->dropIndex('training_program_slots_program_user_session_idx');
            $table->dropColumn('session_index');
        });

        Schema::table('training_programs', function (Blueprint $table): void {
            $table->dropColumn('planned_session_count');
        });
    }
};
