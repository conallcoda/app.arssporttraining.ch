<?php

namespace App\Form\Fields\AthleteGroup;

use App\Cms\Form\Fields\Relationship;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;

class Members extends Relationship
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Members';
        $this->placeholder = 'Select athlete';
        $this->sortable = true;
        $this->default = [];
    }

    public function withOptions(): static
    {
        $this->optionLoader = fn () => User::where('type', UserTypeEnum::Athlete)
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->name])
            ->all();

        return $this;
    }
}
