<?php

namespace App\Models\Training\Factory;

use App\Data\AbstractData;
use App\Models\Training\TrainingNode;

class SeasonFactory extends AbstractData
{

    public static function create(SeasonConfig $config): TrainingNode {}
}
