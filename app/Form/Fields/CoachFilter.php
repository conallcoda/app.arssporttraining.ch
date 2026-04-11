<?php

namespace App\Form\Fields;

use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Coda\FormKit\Fields\Pillbox;

class CoachFilter extends Pillbox
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Coach';
        $this->placeholder = 'Filter by coach...';
        $this->variant = 'listbox';

        $this->optionLoader = fn () => User::whereIn('type', [UserTypeEnum::Coach, UserTypeEnum::Admin])
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->name])
            ->all();
    }
}
