<?php

namespace App\Form\Fields\Training\Plan;

use App\Support\WeekOptions;
use Coda\FormKit\Fields\Select;

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
        $this->optionLoader = fn () => WeekOptions::generate();

        return $this;
    }
}
