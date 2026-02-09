<?php

namespace App\Livewire\Training\View;

use App\Data\Training\Config\TrainingPlanConfig;
use App\Models\TrainingPlan;

class ScheduleHandler
{
    protected TrainingPlanConfig $config;

    public function __construct(
        protected TrainingPlan $trainingPlan,
        protected ?int $userId = null,
    ) {
        $this->config = TrainingPlanConfig::from($this->trainingPlan->config->all());
    }

    public function handle(string $type, array $data = []): void
    {
        match ($type) {
            'add-week' => $this->addWeek(),
        };

        $this->save();
    }

    protected function addWeek(): void
    {
        if ($this->userId !== null) {
            $this->addUserWeek();

            return;
        }

        $this->addDefaultWeek();
    }

    protected function addDefaultWeek(): void
    {
        $weeks = $this->config->defaultScheduleWeeks();
        $week1Id = $weeks[0]['id'] ?? null;
        $maxSort = collect($weeks)->max('sort') ?? count($weeks) - 1;
        $newSort = $maxSort + 1;

        $weeks[] = [
            'id' => "default_{$newSort}",
            'linkedTo' => $week1Id,
            'slots' => [],
            'sort' => $newSort,
        ];

        $this->config->setDefaultScheduleWeeks($weeks);
    }

    protected function addUserWeek(): void
    {
        $defaultWeeks = $this->config->defaultScheduleWeeks();
        $userWeeks = $this->config->userScheduleWeeks($this->userId);

        $allWeeks = collect($defaultWeeks)->merge($userWeeks);
        $firstWeekId = $allWeeks->first()['id'] ?? null;
        $maxSort = $allWeeks->max('sort') ?? $allWeeks->count() - 1;
        $newSort = $maxSort + 1;

        $userWeeks[] = [
            'id' => "user_{$newSort}",
            'linkedTo' => $firstWeekId,
            'slots' => [],
            'sort' => $newSort,
        ];

        $this->config->setUserScheduleWeeks($this->userId, $userWeeks);
    }

    protected function save(): void
    {
        $this->trainingPlan->config = $this->config->toArray();
        $this->trainingPlan->save();
    }
}
