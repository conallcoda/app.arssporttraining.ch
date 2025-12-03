<?php

namespace App\Models\Training\Progression\Strategy\Weight;

use App\Data\AbstractData;
use App\Models\Contracts\HasForms;

abstract class AbstractWeightConfig extends AbstractData implements HasForms, WeightConfigInterface
{
    abstract public function getStrategyClass(): string;

    abstract public function getType(): string;

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'class' => static::class,
            'type' => $this->getType(),
        ]);
    }

    public function getFields(): array
    {
        return [];
    }
}
