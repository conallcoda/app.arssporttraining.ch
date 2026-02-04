<?php

namespace App\Livewire\Training\View;

use App\Data\AbstractData;
use App\Data\Form\Fields\General\Percentage;
use App\Data\Form\Fields\Number;
use App\Data\Form\Fields\Select;
use App\Models\Contracts\HasForms;
use App\Models\TrainingPlan;
use App\Support\WeekOptions;
use App\Training\Reference\RepPercentageTable;

class AthleteTrainingProgramData extends AbstractData implements HasForms
{
    public const DEFAULT_MEASURED_REPS = 8;

    public const DEFAULT_MEASURED_WEIGHT = 52;

    public const DEFAULT_TARGET_GOAL = 7;

    public const DEFAULT_DURATION = 5;

    public function __construct(
        public ?string $start_date = null,
        public ?int $duration = null,
        public ?int $measured_reps = null,
        public ?float $measured_weight = null,
        public ?int $target_goal = null,
        public ?array $programs_selected = null,
    ) {}

    public function estimatedOneRepMax(): ?float
    {
        $reps = $this->measured_reps ?? self::DEFAULT_MEASURED_REPS;
        $weight = $this->measured_weight ?? self::DEFAULT_MEASURED_WEIGHT;

        if ($weight <= 0 || $reps < 1) {
            return null;
        }

        $percentage = RepPercentageTable::getPercentage($reps);

        return round($weight / $percentage, 1);
    }

    public static function fromTrainingPlan(TrainingPlan $trainingPlan, ?int $userId): self
    {
        $key = $userId === null ? 'default' : (string) $userId;
        $data = $trainingPlan->extra->get("users.{$key}.training_plan", []);

        $isDefault = $userId === null;

        $startDate = ! empty($data['start_date']) ? $data['start_date'] : null;
        $duration = ! empty($data['duration']) ? $data['duration'] : null;

        $programsSelected = $data['programs_selected'] ?? null;

        return new self(
            start_date: $startDate ?? ($isDefault ? WeekOptions::getCurrentWeekValue() : null),
            duration: $duration ?? ($isDefault ? self::DEFAULT_DURATION : null),
            measured_reps: $data['measured_reps'] ?? ($isDefault ? self::DEFAULT_MEASURED_REPS : null),
            measured_weight: $data['measured_weight'] ?? ($isDefault ? self::DEFAULT_MEASURED_WEIGHT : null),
            target_goal: $data['target_goal'] ?? ($isDefault ? self::DEFAULT_TARGET_GOAL : null),
            programs_selected: $programsSelected,
        );
    }

    public function persist(TrainingPlan $trainingPlan, ?int $userId): void
    {
        $key = $userId === null ? 'default' : (string) $userId;

        $startDate = $this->start_date ?: null;
        $duration = $this->duration ?: null;
        $targetGoal = $this->target_goal;
        $programsSelected = $this->programs_selected !== null
            ? array_map('intval', $this->programs_selected)
            : null;

        if ($userId !== null) {
            $default = self::fromTrainingPlan($trainingPlan, null);

            if ($startDate === $default->start_date) {
                $startDate = null;
            }
            if ($duration === $default->duration) {
                $duration = null;
            }
            if ($targetGoal === $default->target_goal) {
                $targetGoal = null;
            }
            if ($this->arraysMatch($programsSelected, $default->programs_selected)) {
                $programsSelected = null;
            }
        }

        $trainingPlan->extra->set("users.{$key}.training_plan", [
            'start_date' => $startDate,
            'duration' => $duration,
            'measured_reps' => $this->measured_reps,
            'measured_weight' => $this->measured_weight,
            'target_goal' => $targetGoal,
            'programs_selected' => $programsSelected,
        ]);
        $trainingPlan->save();
    }

    protected function arraysMatch(?array $a, ?array $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        if ($a === null || $b === null) {
            return false;
        }

        $sortedA = $a;
        $sortedB = $b;
        sort($sortedA);
        sort($sortedB);

        return $sortedA === $sortedB;
    }

    public static function getFields(): array
    {
        return [
            Select::make('start_date')
                ->label('Start Date')
                ->options(WeekOptions::generate())
                ->default(WeekOptions::getCurrentWeekValue()),
            Number::make('duration')
                ->label('Duration')
                ->min(1)
                ->step(1)
                ->suffix('weeks')
                ->default(self::DEFAULT_DURATION),
            Number::make('measured_reps')
                ->label('Measured Reps')
                ->min(1)
                ->step(1)
                ->suffix('reps')
                ->default(self::DEFAULT_MEASURED_REPS),
            Number::make('measured_weight')
                ->label('Measured Weight')
                ->min(0)
                ->step(0.5)
                ->suffix('kg')
                ->default(self::DEFAULT_MEASURED_WEIGHT),
            Percentage::make('target_goal')
                ->label('Target Goal')
                ->default(self::DEFAULT_TARGET_GOAL),
        ];
    }
}
