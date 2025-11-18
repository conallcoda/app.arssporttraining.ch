<?php

namespace App\Models\Training;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\Computed;

class TrainingTree extends AbstractData
{
    #[Computed]
    public TrainingNode $tree;
}
