<?php

namespace App\Data\Athlete;

use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use Coda\Cms\Data\AbstractData;

class ProgramDetailsExerciseData extends AbstractData
{
    /**
     * @param  string[]  $equipmentBadges
     * @param  string[]  $modifierBadges
     * @param  string[]  $photoUrls
     * @param  string[]  $weekDetails
     * @param  ProgramDetailsSessionRowData[]  $sessionRows
     * @param  ProgramDetailsNoteData[]  $notes
     * @param  array{light: string, dark: string}  $statusColor
     */
    public function __construct(
        public int $id,
        public int $index,
        public ?string $groupLabel,
        public string $name,
        public array $equipmentBadges = [],
        public array $modifierBadges = [],
        public ?string $instructions = null,
        public ?string $videoUrl = null,
        public array $photoUrls = [],
        public string $setLabel = 'Set',
        public int $setCount = 0,
        public array $sessionRows = [],
        public array $weekDetails = [],
        public array $notes = [],
        public TrainingProgramSlotExerciseStatusEnum $status = TrainingProgramSlotExerciseStatusEnum::Pending,
        public string $statusLabel = '',
        public array $statusColor = ['light' => '', 'dark' => ''],
    ) {}
}
