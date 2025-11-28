<?php

namespace App\Models\Training\ProgressionRules;

use App\Data\AbstractData;

abstract class AbstractProgressionRule extends AbstractData implements ProgressionRule
{
    use TrainingTreeAccessor;
}
