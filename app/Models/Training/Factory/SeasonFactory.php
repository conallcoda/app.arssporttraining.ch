<?php

namespace App\Models\Training;

use App\Data\AbstractData;

class SeasonConfig extends AbstractData
{

    public static function create(SeasonConfig $config): TrainingNode {}
}
