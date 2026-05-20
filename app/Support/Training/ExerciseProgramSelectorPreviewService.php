<?php

namespace App\Support\Training;

use App\Models\Exercise\ExerciseProgram;
use Illuminate\Support\Facades\DB;

class ExerciseProgramSelectorPreviewService
{
    private const PREVIEW_LIMIT = 6;

    public function syncProgram(ExerciseProgram|int|null $program): void
    {
        $programId = $program instanceof ExerciseProgram ? $program->id : (int) $program;

        if ($programId <= 0) {
            return;
        }

        DB::table('exercise_programs')
            ->where('id', $programId)
            ->update($this->payloadForProgram($programId));
    }

    /**
     * @param  iterable<int|string>  $programIds
     */
    public function syncPrograms(iterable $programIds): void
    {
        collect($programIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->each(fn (int $id) => $this->syncProgram($id));
    }

    public function syncProgramsForExercise(int $exerciseId): void
    {
        if ($exerciseId <= 0) {
            return;
        }

        $programIds = DB::table('exercise_program_exercises')
            ->where('exercise_id', $exerciseId)
            ->pluck('exercise_program_id');

        $this->syncPrograms($programIds);
    }

    /**
     * @return array{selector_preview_exercises: ?string, selector_preview_exercise_count: int}
     */
    public function payloadForProgram(int $programId): array
    {
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

        return [
            'selector_preview_exercises' => $names === [] ? null : json_encode($names, JSON_UNESCAPED_UNICODE),
            'selector_preview_exercise_count' => $count,
        ];
    }
}
