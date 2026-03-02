<?php

namespace App\Form\Fields\Training\Calendar;

use Coda\Cms\Form\Fields\RadioSegmented;

class Mode extends RadioSegmented
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'View Mode';
        $this->options = [
            'month' => 'Month',
            'week' => 'Week',
            'day' => 'Day',
            'range' => 'Range',
        ];
        $this->default = 'month';
    }
}
