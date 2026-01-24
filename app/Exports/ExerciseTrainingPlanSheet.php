<?php

namespace App\Exports;

use App\Models\Training\ExercisePlan\AthleteData;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseData;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExerciseTrainingPlanSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        protected AthleteData $athlete,
        protected ExerciseData $exercise,
        protected ExerciseBlock $block
    ) {}

    public function title(): string
    {
        $name = preg_replace('/[^a-zA-Z0-9\s]/', '', $this->exercise->name);

        return substr($name, 0, 31);
    }

    public function array(): array
    {
        $data = [];

        $data[] = [$this->exercise->name.' - '.$this->athlete->name];
        $data[] = ['Group: '.$this->exercise->group];
        $data[] = [];

        $maxSessions = 0;
        foreach ($this->block->weeks as $week) {
            $maxSessions = max($maxSessions, count($week->sessions));
        }

        $headerRow = [''];
        for ($s = 0; $s < $maxSessions; $s++) {
            $headerRow[] = 'Session '.($s + 1);
        }
        $data[] = $headerRow;

        foreach ($this->block->weeks as $weekIndex => $week) {
            $weekNumber = $weekIndex + 1;

            $maxSets = 0;
            foreach ($week->sessions as $session) {
                $maxSets = max($maxSets, count($session->sets));
            }

            for ($setIndex = 0; $setIndex < $maxSets; $setIndex++) {
                $row = [];
                if ($setIndex === 0) {
                    $row[] = 'Week '.$weekNumber;
                } else {
                    $row[] = '';
                }

                foreach ($week->sessions as $session) {
                    if (isset($session->sets[$setIndex])) {
                        $set = $session->sets[$setIndex];
                        $reps = $set->reps ?? '-';
                        $weight = $set->weight !== null ? number_format($set->weight, 1).'kg' : '-';
                        $row[] = $reps.' x '.$weight;
                    } else {
                        $row[] = '';
                    }
                }

                $data[] = $row;
            }

            $data[] = [];
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['italic' => true]],
            4 => ['font' => ['bold' => true]],
        ];
    }
}
