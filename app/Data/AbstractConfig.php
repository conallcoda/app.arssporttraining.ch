<?php

namespace App\Data;

use App\Models\Contracts\HasForms;

abstract class AbstractConfig extends AbstractData implements HasForms
{
    abstract public static function accessor(): string;
}
