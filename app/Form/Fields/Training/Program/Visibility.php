<?php

namespace App\Form\Fields\Training\Program;

use Coda\Cms\Form\Fields\RadioSegmented;

class Visibility extends RadioSegmented
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Visibility';
        $this->options = [
            1 => 'Public',
            0 => 'Private',
        ];
        $this->default = 1;
        $this->helpText = 'Public programs can be used across multiple plans. Private programs are specific to this plan only.';
    }
}
