<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('training_program_slot_exercises', 'exercise_program_exercise_id')) {
            Schema::table('training_program_slot_exercises', function (Blueprint $table) {
                $table->foreignId('exercise_program_exercise_id')
                    ->nullable()
                    ->after('exercise_id')
                    ->index('tps_exercises_program_exercise_idx');
            });
        }

        $this->backfillUniqueExerciseTypeMatches();
        $affectedSlotIds = $this->deleteUnmatchedSlotExercises();
        $this->recalculateSlots($affectedSlotIds);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('training_program_slot_exercises', 'exercise_program_exercise_id')) {
            return;
        }

        if ($this->foreignKeyExists('tps_exercises_program_exercise_fk')) {
            Schema::table('training_program_slot_exercises', function (Blueprint $table) {
                $table->dropForeign('tps_exercises_program_exercise_fk');
            });
        }

        Schema::table('training_program_slot_exercises', function (Blueprint $table) {
            $table->dropIndex('tps_exercises_program_exercise_idx');
            $table->dropColumn('exercise_program_exercise_id');
        });
    }

    private function backfillUniqueExerciseTypeMatches(): void
    {
        DB::statement(<<<'SQL'
            UPDATE training_program_slot_exercises AS tpse
            INNER JOIN training_program_slots AS tps
                ON tps.id = tpse.training_program_slot_id
            INNER JOIN training_programs AS tp
                ON tp.id = tps.training_program_id
            INNER JOIN (
                SELECT
                    exercise_program_id,
                    exercise_id,
                    normalized_type,
                    MIN(id) AS exercise_program_exercise_id
                FROM (
                    SELECT
                        id,
                        exercise_program_id,
                        exercise_id,
                        COALESCE(NULLIF(type, ''), 'main') AS normalized_type
                    FROM exercise_program_exercises
                ) AS normalized_pivots
                GROUP BY
                    exercise_program_id,
                    exercise_id,
                    normalized_type
                HAVING COUNT(*) = 1
            ) AS unique_pivots
                ON unique_pivots.exercise_program_id = tp.exercise_program_id
                AND unique_pivots.exercise_id = tpse.exercise_id
                AND unique_pivots.normalized_type = COALESCE(NULLIF(tpse.type, ''), 'main')
            SET tpse.exercise_program_exercise_id = unique_pivots.exercise_program_exercise_id
            WHERE tpse.exercise_program_exercise_id IS NULL
        SQL);
    }

    private function foreignKeyExists(string $constraintName): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'training_program_slot_exercises')
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    /**
     * @return array<int, int>
     */
    private function deleteUnmatchedSlotExercises(): array
    {
        $query = DB::table('training_program_slot_exercises as tpse')
            ->join('training_program_slots as tps', 'tps.id', '=', 'tpse.training_program_slot_id')
            ->join('training_programs as tp', 'tp.id', '=', 'tps.training_program_id')
            ->whereNull('tpse.exercise_program_exercise_id')
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('exercise_program_exercises as epe')
                    ->whereColumn('epe.exercise_program_id', 'tp.exercise_program_id')
                    ->whereColumn('epe.exercise_id', 'tpse.exercise_id')
                    ->whereRaw("COALESCE(NULLIF(epe.type, ''), 'main') = COALESCE(NULLIF(tpse.type, ''), 'main')");
            });

        $slotIds = (clone $query)
            ->distinct()
            ->pluck('tpse.training_program_slot_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        (clone $query)->delete();

        return $slotIds;
    }

    /**
     * @param  array<int, int>  $slotIds
     */
    private function recalculateSlots(array $slotIds): void
    {
        foreach (array_unique($slotIds) as $slotId) {
            $counts = DB::table('training_program_slot_exercises')
                ->where('training_program_slot_id', $slotId)
                ->selectRaw('COUNT(*) as exercise_count')
                ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
                ->selectRaw("SUM(CASE WHEN status = 'partially_completed' THEN 1 ELSE 0 END) as partial_count")
                ->selectRaw("SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped_count")
                ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count")
                ->selectRaw('MAX(CASE WHEN has_any_modification THEN 1 ELSE 0 END) as has_any_modification')
                ->first();

            $exerciseCount = (int) ($counts->exercise_count ?? 0);
            $completed = (int) ($counts->completed_count ?? 0);
            $partial = (int) ($counts->partial_count ?? 0);
            $skipped = (int) ($counts->skipped_count ?? 0);
            $pending = (int) ($counts->pending_count ?? 0);

            $status = match (true) {
                $exerciseCount === 0 => 'pending',
                $pending === $exerciseCount => 'pending',
                $skipped === $exerciseCount => 'skipped',
                $pending === 0 && $partial === 0 && ($completed + $skipped) === $exerciseCount && $completed > 0 => 'completed',
                default => 'partially_completed',
            };

            DB::table('training_program_slots')
                ->where('id', $slotId)
                ->update([
                    'status' => $status,
                    'exercise_count' => $exerciseCount,
                    'completed_exercise_count' => $completed,
                    'partial_exercise_count' => $partial,
                    'skipped_exercise_count' => $skipped,
                    'pending_exercise_count' => $pending,
                    'has_any_modification' => (bool) ($counts->has_any_modification ?? false),
                ]);
        }
    }
};
