<?php

namespace App\Livewire\Training\View;

use App\Data\AbstractData;
use App\Data\Form\FluxField;
use App\Models\Contracts\HasForms;
use App\Models\Training\Progression\Reference\RepPercentageTable;
use App\Models\TrainingPlan;

class AthleteTrainingProgramData extends AbstractData implements HasForms
{
    public const DEFAULT_MEASURED_REPS = 8;

    public const DEFAULT_MEASURED_WEIGHT = 52;

    public const DEFAULT_TARGET_GOAL = 7;

    public function __construct(
        public ?int $measured_reps = null,
        public ?float $measured_weight = null,
        public ?int $target_goal = null,
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

        return new self(
            measured_reps: $data['measured_reps'] ?? ($isDefault ? self::DEFAULT_MEASURED_REPS : null),
            measured_weight: $data['measured_weight'] ?? ($isDefault ? self::DEFAULT_MEASURED_WEIGHT : null),
            target_goal: $data['target_goal'] ?? ($isDefault ? self::DEFAULT_TARGET_GOAL : null),
        );
    }

    public function persist(TrainingPlan $trainingPlan, ?int $userId): void
    {
        $key = $userId === null ? 'default' : (string) $userId;

        $trainingPlan->extra->set("users.{$key}.training_plan", [
            'measured_reps' => $this->measured_reps,
            'measured_weight' => $this->measured_weight,
            'target_goal' => $this->target_goal,
        ]);
        $trainingPlan->save();
    }

    public static function getFields(): array
    {
        return [
            FluxField::number('measured_reps')
                ->label('Measured Reps')
                ->min(1)
                ->step(1)
                ->suffix('reps')
                ->default(self::DEFAULT_MEASURED_REPS),
            FluxField::number('measured_weight')
                ->label('Measured Weight')
                ->min(0)
                ->step(0.5)
                ->suffix('kg')
                ->default(self::DEFAULT_MEASURED_WEIGHT),
            FluxField::percentage('target_goal')
                ->label('Target Goal')
                ->default(self::DEFAULT_TARGET_GOAL),
        ];
    }
}
