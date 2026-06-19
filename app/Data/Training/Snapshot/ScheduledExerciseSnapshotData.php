<?php

namespace App\Data\Training\Snapshot;

use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use Coda\Cms\Data\AbstractData;

class ScheduledExerciseSnapshotData extends AbstractData
{
    /**
     * @param  string[]  $equipmentBadges
     * @param  string[]  $modifierBadges
     * @param  string[]  $photoUrls
     * @param  array<string, array<string, mixed>>  $settingConfigs
     * @param  ScheduledSetSnapshotData[]  $sets
     * @param  array{light: string, dark: string}  $statusColor
     */
    public function __construct(
        public int $slotExerciseId,
        public int $exerciseId,
        public ?int $programExerciseId,
        public int $sort,
        public ?string $group,
        public string $type,
        public string $name,
        public array $equipmentBadges = [],
        public array $modifierBadges = [],
        public ?string $instructions = null,
        public ?string $videoUrl = null,
        public array $photoUrls = [],
        public string $setLabel = 'Set',
        public array $settingConfigs = [],
        public array $sets = [],
        public TrainingProgramSlotExerciseStatusEnum $status = TrainingProgramSlotExerciseStatusEnum::Pending,
        public string $statusLabel = '',
        public array $statusColor = ['light' => '', 'dark' => ''],
    ) {}
}
