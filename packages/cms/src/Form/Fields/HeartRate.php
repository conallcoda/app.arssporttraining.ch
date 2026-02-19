<?php

namespace Coda\Cms\Form\Fields;

class HeartRate extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->suffix = 'bpm';
    }
}
