<?php

namespace App\Data\Athlete;

use Coda\Cms\Data\AbstractData;

class ProgramDetailsSessionRowData extends AbstractData
{
    /**
     * @param  string[]  $values
     * @param  string[]  $valueClasses
     * @param  bool[]  $modifiedValues
     */
    public function __construct(
        public string $label,
        public string $labelClass = '',
        public array $values = [],
        public array $valueClasses = [],
        public array $modifiedValues = [],
    ) {}
}
