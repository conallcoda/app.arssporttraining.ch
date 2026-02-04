<?php

namespace App\Livewire\Training;

use App\Data\AbstractData;
use App\Data\Form\Fields\Text;
use App\Models\Contracts\HasForms;
use App\Models\TrainingPlan;

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
                'sort' => $user->pivot->sort ?? 0,
            ])->all();
        }

        $userGroups = [];
        if ($plan->relationLoaded('userGroups')) {
            $userGroups = $plan->userGroups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'sort' => $group->pivot->sort ?? 0,
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

        $usersWithSort = collect($this->users)
            ->filter(fn ($user) => ! empty($user['id']))
            ->values()
            ->mapWithKeys(fn ($user, $index) => [
                $user['id'] => ['sort' => $index],
            ])
            ->all();

        $groupsWithSort = collect($this->userGroups)
            ->filter(fn ($group) => ! empty($group['id']))
            ->values()
            ->mapWithKeys(fn ($group, $index) => [
                $group['id'] => ['sort' => $index],
            ])
            ->all();

        $plan->users()->sync($usersWithSort);
        $plan->userGroups()->sync($groupsWithSort);
    }

    public static function getFields(): array
    {
        return [
            Text::make('name')
                ->label('Name')
                ->placeholder('Training plan name')
                ->required()
                ->default('')
                ->rules('required|string|min:1'),
        ];
    }
}
