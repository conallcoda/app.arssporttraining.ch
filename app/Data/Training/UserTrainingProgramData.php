<?php

namespace App\Data\Training;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Form\Fields\Athlete\MeasuredReps;
use App\Form\Fields\Athlete\MeasuredWeight;
use App\Form\Fields\Training\Plan\StartDate;
use App\Form\Fields\Training\Program\TargetGoal;
use App\Models\TrainingPlan;
use App\Training\Reference\RepPercentageTable;

class UserTrainingProgramData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public int $userId,
        public ?string $startDate = null,
        public ?int $measuredReps = null,
        public ?float $measuredWeight = null,
        public ?int $targetGoal = null,
    ) {}

    public function estimatedOneRepMax(): ?float
    {
        if ($this->measuredWeight === null || $this->measuredWeight <= 0) {
            return null;
        }

        if ($this->measuredReps === null || $this->measuredReps < 1) {
            return null;
        }

        $percentage = RepPercentageTable::getPercentage($this->measuredReps);

        return round($this->measuredWeight / $percentage, 1);
    }

    public function resolve(DefaultTrainingProgramData $default): ResolvedTrainingProgramData
    {
        return new ResolvedTrainingProgramData(
            userId: $this->userId,
            startDate: $this->startDate ?? $default->startDate,
            measuredReps: $this->measuredReps ?? $default->measuredReps,
            measuredWeight: $this->measuredWeight ?? $default->measuredWeight,
            targetGoal: $this->targetGoal ?? $default->targetGoal,
        );
    }

    public static function fromTrainingPlan(TrainingPlan $trainingPlan, int $userId): self
    {
        $data = $trainingPlan->config->get("users.{$userId}.exerciseConfig.strength", []);
        $scheduleStartDate = $trainingPlan->config->get("users.{$userId}.schedule.startDate");

        return new self(
            userId: $userId,
            startDate: ! empty($scheduleStartDate) ? $scheduleStartDate : null,
            measuredReps: $data['measuredReps'] ?? null,
            measuredWeight: $data['measuredWeight'] ?? null,
            targetGoal: $data['targetGoal'] ?? null,
        );
    }

    public function persist(TrainingPlan $trainingPlan, DefaultTrainingProgramData $default): void
    {
        $startDate = $this->startDate;
        $targetGoal = $this->targetGoal;

        if ($startDate === $default->startDate) {
            $startDate = null;
        }
        if ($targetGoal === $default->targetGoal) {
            $targetGoal = null;
        }

        $trainingPlan->config->set("users.{$this->userId}.exerciseConfig.strength", [
            'measuredReps' => $this->measuredReps,
            'measuredWeight' => $this->measuredWeight,
            'targetGoal' => $targetGoal,
        ]);

        if ($startDate === null) {
            $trainingPlan->config->forget("users.{$this->userId}.schedule.startDate");
        } else {
            $trainingPlan->config->set("users.{$this->userId}.schedule.startDate", $startDate);
        }

        $trainingPlan->save();
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->withFields([
                StartDate::make('startDate')->withOptions(),
                MeasuredReps::make('measuredReps'),
                MeasuredWeight::make('measuredWeight'),
                TargetGoal::make('targetGoal'),
            ])
            ->fieldset('general', 'General', ['startDate', 'measuredReps', 'measuredWeight', 'targetGoal']);
    }
}
