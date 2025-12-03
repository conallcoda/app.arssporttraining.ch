<?php

namespace App\Models\Training\Progression\Strategy\Rep;

use App\Data\AbstractData;
use App\Models\Contracts\HasForms;

abstract class AbstractRepConfig extends AbstractData implements HasForms, RepConfigInterface
{
    abstract public function getStrategyClass(): string;

    abstract public function getType(): string;

    abstract public function getAnchorRepsForWeek(int $weekIndex, int $blockLength): int;

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
