<?php

namespace App\Support\Training;

class WeekSlotModalPayloadBuilder
{
    public function defaultStartTime(string $period): string
    {
        return $period === 'pm' ? '14:00' : '09:00';
    }

    /**
     * @return array<string, mixed>
     */
    public function forCreate(string $date, string $startTime, int $groupId, ?int $userId): array
    {
        return [
            'date' => $date,
            'start_time' => $startTime,
            'groupId' => $groupId,
            'userId' => $userId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forEdit(int $trainingProgramId, string $date, string $startTime, int $groupId, ?int $userId): array
    {
        return [
            'date' => $date,
            'start_time' => $startTime,
            'training_program_id' => $trainingProgramId,
            'groupId' => $groupId,
            'userId' => $userId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forProgramPrefill(int $trainingProgramId, string $date, int $groupId, ?int $userId): array
    {
        return [
            'date' => $date,
            'start_time' => '09:00',
            'training_program_id' => $trainingProgramId,
            'groupId' => $groupId,
            'userId' => $userId,
            'prefill' => true,
        ];
    }
}
