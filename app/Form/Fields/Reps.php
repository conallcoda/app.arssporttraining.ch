<?php

namespace App\Form\Fields;

use Coda\Cms\Form\Fields\Text;

class Reps extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->suffix = 'rep(s)';
    }
}
