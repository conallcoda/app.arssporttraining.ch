<?php

namespace App\Data\Athlete;

use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use Coda\Cms\Data\AbstractData;

class ScheduledProgramData extends AbstractData
{
    public function __construct(
        public int $slotId,
        public int $trainingProgramId,
        public string $name,
        public string $time,
        public ?string $categoryName = null,
        public ?string $categoryShortName = null,
        public ?string $categoryColor = null,
        public string $period = 'am',
        public int $exerciseCount = 0,
        public array $progressSegments = [],
        public int $recordedExerciseCount = 0,
        public int $totalExerciseCount = 0,
    ) {}

    public static function fromSlot(TrainingProgramSlot $slot): self
    {
        $program = $slot->trainingProgram->program;
        $progressSegments = $slot->exercises
            ->sortBy('sort')
            ->values()
            ->map(fn (TrainingProgramSlotExercise $exercise) => [
                'submitted' => $exercise->status->isSubmitted(),
                'color' => $exercise->status->barColor(),
            ])
            ->all();
        $recordedExerciseCount = count(array_filter($progressSegments, fn (array $segment): bool => $segment['submitted']));
        $totalExerciseCount = count($progressSegments);

        return new self(
            slotId: $slot->id,
            trainingProgramId: $slot->training_program_id,
            name: $program->name,
            time: $slot->datetime->format('H:i'),
            categoryName: $program->exerciseCategory?->name,
            categoryShortName: $program->exerciseCategory
                ? ($program->exerciseCategory->short_name ?: strtoupper(substr($program->exerciseCategory->name, 0, 3)))
                : null,
            categoryColor: $program->exerciseCategory?->color,
            period: $slot->datetime->format('H:i') >= '12:00' ? 'pm' : 'am',
            exerciseCount: $program->exercises
                ->filter(fn ($exercise) => ($exercise->pivot->type ?? 'main') === 'main')
                ->count(),
            progressSegments: $progressSegments,
            recordedExerciseCount: $recordedExerciseCount,
            totalExerciseCount: $totalExerciseCount,
        );
    }
}
