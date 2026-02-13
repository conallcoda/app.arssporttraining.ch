<?php

namespace App\Exports;

use App\Models\Training\ExercisePlan\AthleteData;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AthleteTrainingPlanExport implements WithMultipleSheets
{
    public function __construct(
        protected AthleteData $athlete,
        protected array $groupedBlocks
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->groupedBlocks as $groupName => $exercises) {
            $sheets[] = new GroupTrainingPlanSheet(
                $this->athlete,
                $groupName,
                $exercises
            );
        }

        return $sheets;
    }
}
