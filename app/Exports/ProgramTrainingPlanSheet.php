<?php

namespace App\Exports;

use App\Models\Users\User;
use App\Training\Data\ProgramTrainingPlan;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProgramTrainingPlanSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    protected ProgramTrainingPlan $plan;

    public function __construct(
        User $user,
        string $programName,
        array $exercises,
        ?string $startDate = null
    ) {
        $this->plan = new ProgramTrainingPlan($user, $programName, $exercises, $startDate);
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
        $widths[chr(ord($nextCol) + 1)] = 8;
        $widths[chr(ord($nextCol) + 2)] = 12;
        $widths[chr(ord($nextCol) + 3)] = 12;
        $widths[chr(ord($nextCol) + 4)] = 20;

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = chr(ord('D') + $this->plan->getMaxSets() + 4);

        $rowIndex = 1;
        $rows = $this->plan->getRows();

        foreach ($rows as $row) {
            if ($row['type'] === ProgramTrainingPlan::ROW_TYPE_HEADER) {
                $exerciseRowCount = $row['exerciseRowCount'] ?? 1;
                $endRow = $rowIndex + $exerciseRowCount - 1;

                $sheet->mergeCells("A{$rowIndex}:A{$endRow}");
                $sheet->mergeCells("B{$rowIndex}:B{$endRow}");

                $sheet->getStyle("A{$rowIndex}:A{$endRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getStyle("B{$rowIndex}:B{$endRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("C{$rowIndex}:{$lastColumn}{$rowIndex}")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => ltrim(ProgramTrainingPlan::COLOR_HEADER, '#')],
                    ],
                ]);
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

            $rowIndex++;
        }

        return [];
    }
}
