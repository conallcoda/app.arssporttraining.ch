<?php

namespace App\Form\Fields\Training\Plan;

use App\Form\Fields\Select;
use App\Support\WeekOptions;

class StartDate extends Select
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Start Date';
        $this->default = WeekOptions::getCurrentWeekValue();
    }

    public function withOptions(): static
    {
        $this->options = WeekOptions::generate();

        return $this;
    }
}
