<?php

namespace App\Exports;

use App\Models\Training\ExercisePlan\AthleteData;
use App\Models\Training\ExercisePlan\GroupTrainingPlan;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GroupTrainingPlanSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    protected GroupTrainingPlan $plan;

    public function __construct(
        AthleteData $athlete,
        string $groupName,
        array $exercises
    ) {
        $this->plan = new GroupTrainingPlan($athlete, $groupName, $exercises);
    }

    public function title(): string
    {
        $name = preg_replace('/[^a-zA-Z0-9\s]/', '', $this->plan->getTitle());

        return substr($name, 0, 31);
    }

    public function array(): array
    {
        return $this->plan->toArray();
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 5,
            'B' => 20,
            'C' => 10,
        ];

        for ($i = 0; $i < $this->plan->getMaxSets(); $i++) {
            $column = chr(ord('D') + $i);
            $widths[$column] = 10;
        }

        $nextCol = chr(ord('D') + $this->plan->getMaxSets());
        $widths[$nextCol] = 8;
        $widths[chr(ord($nextCol) + 1)] = 12;
        $widths[chr(ord($nextCol) + 2)] = 12;
        $widths[chr(ord($nextCol) + 3)] = 8;

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = chr(ord('D') + $this->plan->getMaxSets() + 3);

        $styles = [
            1 => [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => ltrim(GroupTrainingPlan::COLOR_HEADER, '#')],
                ],
            ],
        ];

        $rowIndex = 2;
        foreach ($this->plan->getRows() as $row) {
            if ($row['type'] === GroupTrainingPlan::ROW_TYPE_SPACER) {
                $rowIndex++;

                continue;
            }

            $color = $this->plan->getRowColor($row);
            if ($color) {
                $range = "C{$rowIndex}:{$lastColumn}{$rowIndex}";
                $sheet->getStyle($range)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => ltrim($color, '#')],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
            }

            if ($this->plan->isExerciseStartRow($row) && isset($row['exerciseRowspan'])) {
                $endRow = $rowIndex + $row['exerciseRowspan'] - 1;
                $sheet->mergeCells("A{$rowIndex}:A{$endRow}");
                $sheet->mergeCells("B{$rowIndex}:B{$endRow}");
                $sheet->getStyle("A{$rowIndex}:A{$endRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F9FAFB'],
                    ],
                ]);
                $sheet->getStyle("B{$rowIndex}:B{$endRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F9FAFB'],
                    ],
                ]);
            }

            $rowIndex++;
        }

        return $styles;
    }
}
