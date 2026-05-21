<?php

namespace Coda\Cms\Display\DisplayFields;

class Percentage extends Number
{
    public function __construct(string $field)
    {
        parent::__construct($field);

        $this->suffix = '%';
    }
}
