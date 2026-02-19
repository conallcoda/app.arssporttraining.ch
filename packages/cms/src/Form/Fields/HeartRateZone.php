<?php

namespace Coda\Cms\Form\Fields;

class HeartRateZone extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->suffix = 'zone';
    }
}
