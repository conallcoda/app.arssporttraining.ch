<?php

namespace App\Form\Fields\AthleteGroup;

use App\Form\Fields\Pillbox;
use App\Models\Users\UserGroup;

class Groups extends Pillbox
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Groups';
        $this->placeholder = 'Select groups...';
        $this->searchable = true;
    }

    public function withOptions(): static
    {
        $this->options = UserGroup::orderBy('name')
            ->get()
            ->mapWithKeys(fn ($group) => [$group->id => $group->name])
            ->all();

        return $this;
    }
}
