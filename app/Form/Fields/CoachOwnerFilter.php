<?php

namespace App\Form\Fields;

use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Coda\FormKit\Fields\Pillbox;

class CoachOwnerFilter extends Pillbox
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Owner';
        $this->placeholder = 'Filter by owner...';
        $this->variant = 'listbox';
        $this->searchable = true;

        $this->optionLoader = fn () => User::query()
            ->where('type', UserTypeEnum::Coach)
            ->whereNull('owner_id')
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->mapWithKeys(fn (User $user) => [$user->id => $user->name])
            ->all();
    }
}
