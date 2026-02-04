<?php

namespace App\Form\Fields\Athlete;

use App\Form\Fields\Pillbox;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;

class AthletePillbox extends Pillbox
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Athletes';
        $this->placeholder = 'Select athletes...';
        $this->searchable = true;
    }

    public function withOptions(): static
    {
        $this->options = User::where('type', UserTypeEnum::Athlete)
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->name])
            ->all();

        return $this;
    }
}
