<?php

namespace App\Models\Training\Periods\Data;

use App\Data\AbstractData;
use App\Data\Model\ModelIdentity;

class TrainingSessionCategory extends AbstractData
{
    public function __construct(
        public ?ModelIdentity $identity,
        public string $name,
        public ?string $backgroundColor = '#000000',
        public ?string $textColor = '#FFFFFF'
    ) {}
}
