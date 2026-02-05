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
use App\Support\WeekOptions;
use App\Training\Reference\RepPercentageTable;

class DefaultTrainingProgramData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public string $startDate = '',
        public int $duration = 5,
        public int $measuredReps = 8,
        public float $measuredWeight = 52,
        public int $targetGoal = 7,
        public ?array $programsSelected = null,
    ) {
        if ($this->startDate === '') {
            $this->startDate = WeekOptions::getCurrentWeekValue();
        }
    }

    public function estimatedOneRepMax(): ?float
    {
        if ($this->measuredWeight <= 0 || $this->measuredReps < 1) {
            return null;
        }

        $percentage = RepPercentageTable::getPercentage($this->measuredReps);

        return round($this->measuredWeight / $percentage, 1);
    }

    public static function fromTrainingPlan(TrainingPlan $trainingPlan): self
    {
        $data = $trainingPlan->config->get('users.default.training_plan', []);

        return new self(
            startDate: ! empty($data['startDate']) ? $data['startDate'] : WeekOptions::getCurrentWeekValue(),
            duration: $data['duration'] ?? 5,
            measuredReps: $data['measuredReps'] ?? 8,
            measuredWeight: $data['measuredWeight'] ?? 52,
            targetGoal: $data['targetGoal'] ?? 7,
            programsSelected: $data['programsSelected'] ?? null,
        );
    }

    public function persist(TrainingPlan $trainingPlan): void
    {
        $trainingPlan->config->set('users.default.training_plan', [
            'startDate' => $this->startDate,
            'duration' => $this->duration,
            'measuredReps' => $this->measuredReps,
            'measuredWeight' => $this->measuredWeight,
            'targetGoal' => $this->targetGoal,
            'programsSelected' => $this->programsSelected,
        ]);
        $trainingPlan->save();
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
