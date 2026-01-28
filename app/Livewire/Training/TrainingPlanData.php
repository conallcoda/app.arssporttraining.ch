<?php

namespace App\Livewire\Training;

use App\Data\AbstractData;
use App\Data\Form\FluxField;
use App\Models\Contracts\HasForms;
use App\Models\TrainingPlan;

class TrainingPlanData extends AbstractData implements HasForms
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $startDate = null,
        public ?int $duration = null,
        public array $users = [],
        public array $userGroups = [],
    ) {}

    public static function fromTrainingPlan(TrainingPlan $plan): self
    {
        $users = [];
        if ($plan->relationLoaded('users')) {
            $users = $plan->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])->all();
        }

        $userGroups = [];
        if ($plan->relationLoaded('userGroups')) {
            $userGroups = $plan->userGroups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
            ])->all();
        }

        return new self(
            id: $plan->id,
            name: $plan->name ?? '',
            startDate: $plan->start_date?->format('Y-m-d'),
            duration: $plan->duration,
            users: $users,
            userGroups: $userGroups,
        );
    }

    public function persist(): void
    {
        if ($this->id === null) {
            $plan = TrainingPlan::create([
                'name' => $this->name,
                'start_date' => $this->startDate,
                'duration' => $this->duration,
            ]);
            $this->id = $plan->id;
        } else {
            $plan = TrainingPlan::findOrFail($this->id);
            $plan->name = $this->name;
            $plan->start_date = $this->startDate;
            $plan->duration = $this->duration;
            $plan->save();
        }

        $userIds = collect($this->users)
            ->filter(fn ($user) => ! empty($user['id']))
            ->pluck('id')
            ->all();

        $groupIds = collect($this->userGroups)
            ->filter(fn ($group) => ! empty($group['id']))
            ->pluck('id')
            ->all();

        $plan->users()->sync($userIds);
        $plan->userGroups()->sync($groupIds);
    }

    public static function example(int $id = 1, string $name = 'Training Plan A'): self
    {
        return new self(
            id: $id,
            name: $name,
        );
    }

    public static function getFields(): array
    {
        return [
            FluxField::text('name')
                ->label('Name')
                ->placeholder('Training plan name')
                ->required()
                ->default('')
                ->rules('required|string|min:1'),
        ];
    }
}
