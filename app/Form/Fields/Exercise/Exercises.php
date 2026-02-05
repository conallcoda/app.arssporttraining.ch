<?php

namespace App\Form\Fields\Exercise;

use App\Cms\Form\Fields\Relationship;
use App\Models\Exercise\Exercise;

class Exercises extends Relationship
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Exercises';
        $this->placeholder = 'Select exercise';
        $this->sortable = true;
        $this->default = [];
    }

    public function withOptions(): static
    {
        $this->options = Exercise::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($exercise) => [$exercise->id => $exercise->name])
            ->all();

        return $this;
    }
}
