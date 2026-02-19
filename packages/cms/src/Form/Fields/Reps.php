<?php

namespace Coda\Cms\Form\Fields;

class Reps extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->suffix = 'rep(s)';
    }
}
