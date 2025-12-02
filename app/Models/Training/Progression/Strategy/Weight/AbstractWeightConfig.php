<?php

namespace App\Models\Training\Progression\Strategy\Weight;

use App\Data\AbstractData;
use App\Models\Contracts\HasForms;
use Filament\Schemas\Schema;

abstract class AbstractWeightConfig extends AbstractData implements HasForms, WeightConfigInterface
{
    abstract public function getStrategyClass(): string;

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'class' => static::class,
        ]);
    }

    public function getFields(): array
    {
        return [];
    }

    public function getForm(Schema $schema): array
    {
        return $schema->components($this->getFields())->getComponents();
    }
}
