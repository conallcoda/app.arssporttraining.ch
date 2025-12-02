<?php

namespace App\Models\Training\Progression\Strategy\Rep;

use App\Data\AbstractData;
use App\Models\Contracts\HasForms;
use Filament\Schemas\Schema;

abstract class AbstractRepConfig extends AbstractData implements HasForms, RepConfigInterface
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
