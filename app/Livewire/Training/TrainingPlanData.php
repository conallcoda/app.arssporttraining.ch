<?php

namespace App\Livewire\Training;

use App\Data\AbstractData;
use App\Data\Form\FluxField;
use App\Models\Contracts\HasForms;
use App\Models\TrainingPlan;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Models\Users\UserTypeEnum;

class TrainingPlanData extends AbstractData implements HasForms
{
    public function __construct(
        public ?int $id,
        public string $name,
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
            users: $users,
            userGroups: $userGroups,
        );
    }

    public function persist(): void
    {
        if ($this->id === null) {
            $plan = TrainingPlan::create([
                'name' => $this->name,
            ]);
            $this->id = $plan->id;
        } else {
            $plan = TrainingPlan::findOrFail($this->id);
            $plan->name = $this->name;
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
        $athleteOptions = User::where('type', UserTypeEnum::Athlete)
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->name])
            ->all();

        $groupOptions = UserGroup::orderBy('name')
            ->get()
            ->mapWithKeys(fn ($group) => [$group->id => $group->name])
            ->all();

        return [
            FluxField::text('name')
                ->label('Name')
                ->placeholder('Training plan name')
                ->required()
                ->default('')
                ->rules('required|string|min:1'),
            FluxField::relationship('users')
                ->label('Athletes')
                ->options($athleteOptions)
                ->placeholder('Select athlete')
                ->default([]),
            FluxField::relationship('userGroups')
                ->label('Groups')
                ->options($groupOptions)
                ->placeholder('Select group')
                ->default([]),
        ];
    }
}
