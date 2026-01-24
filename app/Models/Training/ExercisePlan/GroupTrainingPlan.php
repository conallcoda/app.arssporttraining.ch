<?php

namespace App\Models\Training\ExercisePlan;

class GroupTrainingPlan
{
    public const COLOR_HEADER = '#E0E0E0';

    public const COLOR_REPS = '#DBEAFE';

    public const COLOR_WEIGHT = '#DCFCE7';

    public const COLOR_ONE_REP_MAX = '#FFEDD5';

    public const ROW_TYPE_HEADER = 'header';

    public const ROW_TYPE_REPS = 'reps';

    public const ROW_TYPE_WEIGHT = 'weight';

    public const ROW_TYPE_SPACER = 'spacer';

    public const MAX_SETS = 6;

    protected array $rows = [];

    protected array $headers = [];

    protected int $maxWeeks = 0;

    protected int $maxSets = 0;

    public function __construct(
        public readonly AthleteData $athlete,
        public readonly string $groupName,
        public readonly array $exercises
    ) {
        $this->calculateDimensions();
        $this->build();
    }

    public function getTitle(): string
    {
        return $this->groupName;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function getMaxSets(): int
    {
        return $this->maxSets;
    }

    public function getMaxWeeks(): int
    {
        return $this->maxWeeks;
    }

    public function getRowColor(array $row): string
    {
        return match ($row['type']) {
            self::ROW_TYPE_HEADER => self::COLOR_HEADER,
            self::ROW_TYPE_REPS => self::COLOR_REPS,
            self::ROW_TYPE_WEIGHT => self::COLOR_WEIGHT,
            default => '',
        };
    }

    public function getTailwindRowClass(array $row): string
    {
        return match ($row['type']) {
            self::ROW_TYPE_HEADER => 'bg-zinc-200 dark:bg-zinc-700',
            self::ROW_TYPE_REPS => 'bg-blue-50 dark:bg-blue-900/20',
            self::ROW_TYPE_WEIGHT => 'bg-green-50 dark:bg-green-900/20',
            default => '',
        };
    }

    public function isExerciseStartRow(array $row): bool
    {
        return $row['isExerciseStart'] ?? false;
    }

    protected function calculateDimensions(): void
    {
        foreach ($this->exercises as $item) {
            $this->maxWeeks = max($this->maxWeeks, count($item['block']->weeks));
            foreach ($item['block']->weeks as $week) {
                foreach ($week->sessions as $session) {
                    $this->maxSets = max($this->maxSets, count($session->sets));
                }
            }
        }

        $this->maxSets = max($this->maxSets, self::MAX_SETS);
    }

    protected function build(): void
    {
        $this->headers = ['#', 'Exercise', ''];
        for ($i = 1; $i <= $this->maxSets; $i++) {
            $this->headers[] = 'Set '.$i;
        }
        $this->headers[] = 'TUT';
        $this->headers[] = 'Datum';
        $this->headers[] = 'Datum';
        $this->headers[] = 'Week';

        foreach ($this->exercises as $exerciseIndex => $item) {
            $exercise = $item['exercise'];
            $block = $item['block'];
            $exerciseNumber = $exerciseIndex + 1;

            $this->buildExerciseRows($exercise, $exerciseNumber, $block);

            $this->rows[] = [
                'type' => self::ROW_TYPE_SPACER,
                'isExerciseStart' => false,
                'isWeekStart' => false,
                'cells' => [],
            ];
        }
    }

    protected function buildExerciseRows(ExerciseData $exercise, int $exerciseNumber, ExerciseBlock $block): void
    {
        $totalRows = $this->calculateTotalRowsForExercise($block);
        $isFirstRow = true;
        $weekNumber = 1;

        foreach ($block->weeks as $week) {
            $sessions = $week->sessions;
            if (empty($sessions)) {
                continue;
            }

            $firstSession = $sessions[0];

            $this->rows[] = [
                'type' => self::ROW_TYPE_REPS,
                'isExerciseStart' => $isFirstRow,
                'exerciseNumber' => $isFirstRow ? $exerciseNumber : null,
                'exerciseName' => $isFirstRow ? $exercise->name : null,
                'exerciseRowspan' => $isFirstRow ? $totalRows : null,
                'label' => 'Reps',
                'cells' => $this->buildSetCells($firstSession, 'reps'),
                'tut' => '',
                'datum1' => '',
                'datum2' => '',
                'weekLabel' => '',
            ];
            $isFirstRow = false;

            foreach ($sessions as $session) {
                $this->rows[] = [
                    'type' => self::ROW_TYPE_WEIGHT,
                    'isExerciseStart' => false,
                    'label' => 'Weight',
                    'cells' => $this->buildSetCells($session, 'weight'),
                    'tut' => '2010',
                    'datum1' => '',
                    'datum2' => '',
                    'weekLabel' => 'W'.$weekNumber,
                ];
                $weekNumber++;
            }
        }
    }

    protected function calculateTotalRowsForExercise(ExerciseBlock $block): int
    {
        $total = 0;
        foreach ($block->weeks as $week) {
            if (! empty($week->sessions)) {
                $total += 1 + count($week->sessions);
            }
        }

        return $total;
    }

    protected function buildSetCells(ExerciseSession $session, string $field): array
    {
        $cells = [];

        for ($i = 0; $i < $this->maxSets; $i++) {
            if (isset($session->sets[$i])) {
                $set = $session->sets[$i];
                $value = match ($field) {
                    'reps' => $set->reps,
                    'weight' => $set->weight !== null ? number_format($set->weight, 1) : null,
                    default => null,
                };
                $cells[] = $value ?? '-';
            } else {
                $cells[] = '';
            }
        }

        return $cells;
    }

    public function toArray(): array
    {
        $data = [];
        $data[] = $this->headers;

        foreach ($this->rows as $row) {
            if ($row['type'] === self::ROW_TYPE_SPACER) {
                $data[] = [];

                continue;
            }

            $rowData = [];

            if ($row['isExerciseStart'] ?? false) {
                $rowData[] = $row['exerciseNumber'] ?? '';
                $rowData[] = $row['exerciseName'] ?? '';
            } else {
                $rowData[] = '';
                $rowData[] = '';
            }

            $rowData[] = $row['label'] ?? '';

            foreach ($row['cells'] as $cell) {
                $rowData[] = $cell;
            }

            $rowData[] = $row['tut'] ?? '';
            $rowData[] = $row['datum1'] ?? '';
            $rowData[] = $row['datum2'] ?? '';
            $rowData[] = $row['weekLabel'] ?? '';

            $data[] = $rowData;
        }

        return $data;
    }
}
