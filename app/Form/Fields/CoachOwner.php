<?php

namespace App\Form\Fields;

use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;

class CoachOwner extends Owner
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Owner';
        $this->placeholder = 'Select owner...';
        $this->default = auth()->user()?->type === UserTypeEnum::Coach && auth()->user()?->owner_id === null
            ? auth()->id()
            : null;
        $this->validationRules = 'required|integer|exists:users,id';
        $this->required = true;
    }

    public function withOptions(): static
    {
        $this->optionLoader = fn () => User::query()
            ->where('type', UserTypeEnum::Coach)
            ->whereNull('owner_id')
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->mapWithKeys(fn (User $user) => [$user->id => $user->name])
            ->all();

        return $this;
    }
}
