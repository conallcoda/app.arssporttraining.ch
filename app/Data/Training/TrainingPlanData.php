<?php

namespace App\Data\Training;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Form\Fields\Training\Plan\PlanName;
use App\Models\TrainingPlan;

class TrainingPlanData extends AbstractData implements HasForms
{
    use InteractsWithForms;

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
        $plan = TrainingPlan::updateOrCreate(
            ['id' => $this->id],
            ['name' => $this->name]
        );

        $this->id = $plan->id;

        $plan->syncSortableRelation($plan->users(), $this->users);
        $plan->syncSortableRelation($plan->userGroups(), $this->userGroups);
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->withFields([
                PlanName::make('name'),
            ])
            ->fieldset('general', 'General', ['name']);
    }
}
