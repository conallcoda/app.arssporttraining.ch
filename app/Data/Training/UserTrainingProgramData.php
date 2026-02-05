<?php

namespace App\Data\Training;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Form\Fields\Athlete\MeasuredReps;
use App\Form\Fields\Athlete\MeasuredWeight;
use App\Form\Fields\Training\Plan\Duration;
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
        public ?int $duration = null,
        public ?int $measuredReps = null,
        public ?float $measuredWeight = null,
        public ?int $targetGoal = null,
        public ?array $programsSelected = null,
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
            duration: $this->duration ?? $default->duration,
            measuredReps: $this->measuredReps ?? $default->measuredReps,
            measuredWeight: $this->measuredWeight ?? $default->measuredWeight,
            targetGoal: $this->targetGoal ?? $default->targetGoal,
            programsSelected: $this->programsSelected ?? $default->programsSelected,
        );
    }

    public static function fromTrainingPlan(TrainingPlan $trainingPlan, int $userId): self
    {
        $data = $trainingPlan->config->get("users.{$userId}.training_plan", []);

        return new self(
            userId: $userId,
            startDate: ! empty($data['startDate']) ? $data['startDate'] : null,
            duration: ! empty($data['duration']) ? $data['duration'] : null,
            measuredReps: $data['measuredReps'] ?? null,
            measuredWeight: $data['measuredWeight'] ?? null,
            targetGoal: $data['targetGoal'] ?? null,
            programsSelected: $data['programsSelected'] ?? null,
        );
    }

    public function persist(TrainingPlan $trainingPlan, DefaultTrainingProgramData $default): void
    {
        $startDate = $this->startDate;
        $duration = $this->duration;
        $targetGoal = $this->targetGoal;
        $programsSelected = $this->programsSelected !== null
            ? array_map('intval', $this->programsSelected)
            : null;

        if ($startDate === $default->startDate) {
            $startDate = null;
        }
        if ($duration === $default->duration) {
            $duration = null;
        }
        if ($targetGoal === $default->targetGoal) {
            $targetGoal = null;
        }
        if ($this->arraysMatch($programsSelected, $default->programsSelected)) {
            $programsSelected = null;
        }

        $trainingPlan->config->set("users.{$this->userId}.training_plan", [
            'startDate' => $startDate,
            'duration' => $duration,
            'measuredReps' => $this->measuredReps,
            'measuredWeight' => $this->measuredWeight,
            'targetGoal' => $targetGoal,
            'programsSelected' => $programsSelected,
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

    public static function getForm(): Form
    {
        return Form::make()
            ->withFields([
                StartDate::make('startDate')->withOptions(),
                Duration::make('duration'),
                MeasuredReps::make('measuredReps'),
                MeasuredWeight::make('measuredWeight'),
                TargetGoal::make('targetGoal'),
            ])
            ->fieldset('general', 'General', ['startDate', 'duration', 'measuredReps', 'measuredWeight', 'targetGoal']);
    }
}
