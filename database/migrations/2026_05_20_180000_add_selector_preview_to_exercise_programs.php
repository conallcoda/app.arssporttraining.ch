<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PREVIEW_LIMIT = 6;

    public function up(): void
    {
        Schema::table('exercise_programs', function (Blueprint $table) {
            $table->json('selector_preview_exercises')->nullable()->after('config');
            $table->unsignedInteger('selector_preview_exercise_count')->default(0)->after('selector_preview_exercises');
            $table->index(['type', 'parent_id', 'deleted_at', 'name'], 'exercise_program_selector_lookup_idx');
        });

        Schema::table('exercise_program_exercises', function (Blueprint $table) {
            $table->index(['exercise_program_id', 'type', 'group', 'sort', 'id'], 'epe_selector_preview_idx');
        });

        DB::table('exercise_programs')
            ->select('id')
            ->orderBy('id')
            ->chunk(100, function ($programs): void {
                foreach ($programs as $program) {
                    $programId = (int) $program->id;
                    $baseQuery = DB::table('exercise_program_exercises as epe')
                        ->join('exercises as e', 'e.id', '=', 'epe.exercise_id')
                        ->where('epe.exercise_program_id', $programId)
                        ->where('epe.type', 'main')
                        ->whereNull('e.deleted_at');

                    $count = (clone $baseQuery)->count();
                    $names = (clone $baseQuery)
                        ->orderByRaw("case when epe.`group` is null or epe.`group` = '' then 0 else 1 end")
                        ->orderBy('epe.group')
                        ->orderBy('epe.sort')
                        ->orderBy('epe.id')
                        ->limit(self::PREVIEW_LIMIT)
                        ->pluck('e.name')
                        ->map(fn (mixed $name): string => (string) $name)
                        ->values()
                        ->all();

                    DB::table('exercise_programs')
                        ->where('id', $programId)
                        ->update([
                            'selector_preview_exercises' => $names === [] ? null : json_encode($names, JSON_UNESCAPED_UNICODE),
                            'selector_preview_exercise_count' => $count,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('exercise_program_exercises', function (Blueprint $table) {
            $table->dropIndex('epe_selector_preview_idx');
        });

        Schema::table('exercise_programs', function (Blueprint $table) {
            $table->dropIndex('exercise_program_selector_lookup_idx');
            $table->dropColumn([
                'selector_preview_exercises',
                'selector_preview_exercise_count',
            ]);
        });
    }
};
