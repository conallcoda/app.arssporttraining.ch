<?php

namespace App\Data\Athlete;

use Coda\Cms\Data\AbstractData;

class ProgramDetailsNoteData extends AbstractData
{
    public function __construct(
        public string $label,
        public string $value,
    ) {}
}
