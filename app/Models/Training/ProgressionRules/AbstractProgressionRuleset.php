<?php

namespace App\Models\Training\ProgressionRules;

use App\Data\AbstractData;

abstract class AbstractProgressionRuleset extends AbstractData implements ProgressionRuleset
{
    use TrainingTreeAccessor;
}
