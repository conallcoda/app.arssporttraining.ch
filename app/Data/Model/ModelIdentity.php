<?php

namespace App\Data\Model;

use App\Data\AbstractData;

class ModelIdentity extends AbstractData
{
    public function __construct(public int $id, public string $model) {}
}
