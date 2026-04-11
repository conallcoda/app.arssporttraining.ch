<?php

namespace App\Form\Fields\Training\Calendar;

use Coda\FormKit\Fields\RadioSegmented;

class TimePeriod extends RadioSegmented
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Period';
        $this->options = [
            'month' => 'Month',
            'week' => 'Week',
            'day' => 'Day',
            'range' => 'Range',
        ];
        $this->default = 'month';
    }
}
