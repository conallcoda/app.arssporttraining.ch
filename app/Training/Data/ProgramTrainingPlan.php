<?php

namespace App\Training\Data;

use App\Models\Users\User;
use Carbon\Carbon;

class ProgramTrainingPlan
{
    public const COLOR_HEADER = '#E0E0E0';

    public const COLOR_REPS = '#DBEAFE';

    public const COLOR_WEIGHT = '#DCFCE7';

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
        public readonly User $user,
        public readonly string $programName,
        public readonly array $exercises,
        public readonly ?string $startDate = null
    ) {
        $this->calculateDimensions();
        $this->build();
    }

    public function getTitle(): string
    {
        return $this->programName;
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

    public function isExerciseHeaderRow(array $row): bool
    {
        return $row['type'] === self::ROW_TYPE_HEADER;
    }

    protected function calculateDimensions(): void
    {
        foreach ($this->exercises as $item) {
            $block = $item['block'];
            $this->maxWeeks = max($this->maxWeeks, count($block->weeks));
            foreach ($block->weeks as $week) {
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
        $this->headers[] = 'Rest';
        $this->headers[] = 'Date';
        $this->headers[] = 'Date';
        $this->headers[] = 'Week';

        foreach ($this->exercises as $exerciseIndex => $item) {
            $exercise = $item['exercise'];
            $block = $item['block'];
            $exerciseNumber = $exerciseIndex + 1;

            $exerciseRowCount = $this->calculateExerciseRowCount($block);

            $exerciseHeader = [$exerciseNumber, $exercise->name, ''];
            for ($i = 1; $i <= $this->maxSets; $i++) {
                $exerciseHeader[] = 'Set '.$i;
            }
            $exerciseHeader[] = 'TUT';
            $exerciseHeader[] = 'Rest';
            $exerciseHeader[] = 'Date';
            $exerciseHeader[] = 'Date';
            $exerciseHeader[] = 'Week';

            $this->rows[] = [
                'type' => self::ROW_TYPE_HEADER,
                'exerciseNumber' => $exerciseNumber,
                'exerciseName' => $exercise->name,
                'headerCells' => $exerciseHeader,
                'exerciseRowCount' => $exerciseRowCount,
            ];

            $tut = $item['tut'] ?? '3010';
            $rest = (int) ($item['rest'] ?? 30);
            $weekOverrides = $item['weekOverrides'] ?? [];
            $this->buildExerciseRows($block, $tut, $rest, $weekOverrides);
        }
    }

    protected function buildExerciseRows(TrainingBlock $block, string $tut, int $rest, array $weekOverrides = []): void
    {
        $weekIndex = 0;
        $lastReps = null;

        foreach ($block->weeks as $week) {
            $sessions = $week->sessions;
            if (empty($sessions)) {
                continue;
            }

            $firstSession = $sessions[0];
            $currentReps = $this->getRepsArray($firstSession);

            $weekData = $this->findWeekOverrideData($weekOverrides, $weekIndex);
            $weekTut = $weekData['tut'] ?? $tut;
            $weekRest = (int) ($weekData['rest'] ?? $rest);

            if (! $this->repsAreSimilar($lastReps, $currentReps)) {
                $this->rows[] = [
                    'type' => self::ROW_TYPE_REPS,
                    'label' => 'Reps',
                    'cells' => $this->buildSetCells($firstSession, 'reps'),
                    'tut' => '',
                    'rest' => '',
                    'date1' => '',
                    'date2' => '',
                    'week' => '',
                ];
                $lastReps = $currentReps;
            }

            $this->rows[] = [
                'type' => self::ROW_TYPE_WEIGHT,
                'label' => 'Weight',
                'cells' => $this->buildSetCells($firstSession, 'weight'),
                'tut' => $weekTut,
                'rest' => $weekRest,
                'date1' => '',
                'date2' => '',
                'week' => $this->getCombinedWeekLabel($weekIndex),
            ];
            $weekIndex++;
        }
    }

    protected function getCombinedWeekLabel(int $weekIndex): string
    {
        $trainingWeek = 'TW '.($weekIndex + 1);

        if (! $this->startDate) {
            return $trainingWeek;
        }

        $date = Carbon::parse($this->startDate)->addWeeks($weekIndex);

        return $trainingWeek.' ('.$date->year.' - W'.$date->isoWeek().')';
    }

    protected function calculateExerciseRowCount(TrainingBlock $block): int
    {
        $count = 1;
        $lastReps = null;

        foreach ($block->weeks as $week) {
            if (empty($week->sessions)) {
                continue;
            }

            $firstSession = $week->sessions[0];
            $currentReps = $this->getRepsArray($firstSession);

            if (! $this->repsAreSimilar($lastReps, $currentReps)) {
                $count++;
                $lastReps = $currentReps;
            }

            $count++;
        }

        return $count;
    }

    protected function getRepsArray(TrainingSession $session): array
    {
        $reps = [];
        foreach ($session->sets as $set) {
            $reps[] = $set->reps;
        }

        return $reps;
    }

    protected function repsAreSimilar(?array $lastReps, array $currentReps): bool
    {
        if ($lastReps === null) {
            return false;
        }

        $minLength = min(count($lastReps), count($currentReps));
        for ($i = 0; $i < $minLength; $i++) {
            if ($lastReps[$i] !== $currentReps[$i]) {
                return false;
            }
        }

        return true;
    }

    protected function buildSetCells(TrainingSession $session, string $field): array
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

    protected function findWeekOverrideData(array $overrides, int $weekIndex): array
    {
        foreach ($overrides as $override) {
            if ($override['week'] === $weekIndex) {
                return $override['data'] ?? [];
            }
        }

        return [];
    }

    public function toArray(): array
    {
        $data = [];

        foreach ($this->rows as $row) {
            if ($row['type'] === self::ROW_TYPE_HEADER) {
                $data[] = $row['headerCells'];

                continue;
            }

            $rowData = ['', '', $row['label'] ?? ''];

            foreach ($row['cells'] as $cell) {
                $rowData[] = $cell;
            }

            $rowData[] = $row['tut'] ?? '';
            $rowData[] = $row['rest'] ?? '';
            $rowData[] = $row['date1'] ?? '';
            $rowData[] = $row['date2'] ?? '';
            $rowData[] = $row['week'] ?? '';

            $data[] = $rowData;
        }

        return $data;
    }
}
