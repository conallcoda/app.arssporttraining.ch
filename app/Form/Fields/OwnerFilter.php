<?php

namespace App\Form\Fields;

use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Coda\FormKit\Fields\Pillbox;

class OwnerFilter extends Pillbox
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Owner';
        $this->placeholder = 'Filter by owner...';
        $this->variant = 'listbox';
        $this->searchable = true;

        $this->optionLoader = fn () => User::whereIn('type', [UserTypeEnum::Coach, UserTypeEnum::Admin])
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->name])
            ->all();
    }
}
