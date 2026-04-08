<?php

namespace App\Data\Athlete;

use App\Models\Training\TrainingProgramSlot;
use Coda\Cms\Data\AbstractData;

class ScheduledProgramData extends AbstractData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $categoryName = null,
        public ?string $categoryColor = null,
        public string $period = 'am',
        public int $exerciseCount = 0,
    ) {}

    public static function fromSlot(TrainingProgramSlot $slot): self
    {
        $program = $slot->trainingProgram->program;

        return new self(
            id: $slot->id,
            name: $program->name,
            categoryName: $program->exerciseCategory?->name,
            categoryColor: $program->exerciseCategory?->color,
            period: $slot->datetime->format('H:i') >= '12:00' ? 'pm' : 'am',
            exerciseCount: $program->exercises->count(),
        );
    }
}
