<?php

namespace App\Form\Fields;

use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Coda\FormKit\Fields\SelectEntity;

class Owner extends SelectEntity
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Owner';
        $this->placeholder = 'Select owner...';
        $this->searchable = true;
        $this->clearable = true;
        $this->validationRules = 'nullable|integer|exists:users,id';
        $this->default = auth()->id();
    }

    public function withOptions(): static
    {
        $this->optionLoader = fn () => User::whereIn('type', [UserTypeEnum::Coach, UserTypeEnum::Admin])
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->name])
            ->all();

        return $this;
    }

    public function allowNoOwner(): static
    {
        $this->withExtraOption('No owner', 0);

        $this->validationRules = 'required|integer';
        $this->required = true;
        $this->placeholder = '';
        $this->clearable = false;
        $this->variant = 'listbox';

        return $this;
    }
}
