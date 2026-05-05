<?php

namespace App\Support\Training;

use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\UserGroup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AthletePreviewSlotService
{
    private const PREVIEW_SORT = -999999;

    public function createPreviewProgram(
        ExerciseProgram $exerciseProgram,
        ?int $userId,
        int $weeks,
        int $sessionsPerWeek,
        array $weekSessionDates = [],
        array $weekSessions = [],
    ): TrainingProgram {
        abort_if($userId === null, 404);

        $groupId = $this->resolveGroupId($exerciseProgram, $userId);
        abort_if($groupId === null, 404);

        return DB::transaction(function () use ($exerciseProgram, $userId, $weeks, $sessionsPerWeek, $weekSessionDates, $weekSessions, $groupId): TrainingProgram {
            $this->clearExistingPreviews($exerciseProgram->id, $groupId);

            $trainingProgram = TrainingProgram::query()->create([
                'group_id' => $groupId,
                'exercise_program_id' => $exerciseProgram->id,
                'sort' => self::PREVIEW_SORT,
                'owner_id' => Auth::id(),
            ]);

            $slotDates = $this->resolveSlotDates($weeks, $sessionsPerWeek, $weekSessionDates, $weekSessions);

            foreach ($slotDates as $index => $date) {
                TrainingProgramSlot::query()->create([
                    'training_program_id' => $trainingProgram->id,
                    'user_id' => $userId,
                    'datetime' => $date->setTime(8 + (($index % 3) * 3), 0),
                    'owner_id' => Auth::id(),
                ]);
            }

            return $trainingProgram->fresh();
        });
    }

    private function resolveGroupId(ExerciseProgram $exerciseProgram, int $userId): ?int
    {
        if ($exerciseProgram->parent_type === TrainingProgram::class && $exerciseProgram->parent_id !== null) {
            return TrainingProgram::query()->find($exerciseProgram->parent_id)?->group_id;
        }

        return UserGroup::query()
            ->whereHas('members', fn ($query) => $query->where('users.id', $userId))
            ->value('id');
    }

    private function clearExistingPreviews(int $exerciseProgramId, int $groupId): void
    {
        $programs = TrainingProgram::query()
            ->where('exercise_program_id', $exerciseProgramId)
            ->where('group_id', $groupId)
            ->where('owner_id', Auth::id())
            ->where('sort', self::PREVIEW_SORT)
            ->get();

        foreach ($programs as $program) {
            $program->slots()->delete();
            $program->delete();
        }
    }

    /**
     * @return CarbonImmutable[]
     */
    private function resolveSlotDates(int $weeks, int $sessionsPerWeek, array $weekSessionDates, array $weekSessions): array
    {
        $dates = [];
        $cursor = CarbonImmutable::today()->next('monday');

        for ($weekIndex = 0; $weekIndex < max($weeks, 1); $weekIndex++) {
            $count = max((int) ($weekSessions[$weekIndex] ?? 0), count($weekSessionDates[$weekIndex] ?? []), $sessionsPerWeek, 1);

            for ($sessionIndex = 0; $sessionIndex < $count; $sessionIndex++) {
                $provided = $weekSessionDates[$weekIndex][$sessionIndex] ?? null;

                if (is_string($provided) && $provided !== '') {
                    $dates[] = CarbonImmutable::parse($provided);

                    continue;
                }

                $dates[] = $cursor->addWeeks($weekIndex)->addDays($sessionIndex);
            }
        }

        return $dates;
    }
}
