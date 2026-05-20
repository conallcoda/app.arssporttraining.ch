<?php

namespace Coda\Cms\Tests\Fixtures;

use Coda\Cms\Display\CardDefinition;
use Coda\Cms\Display\CardField;

class TestCardChildItemTree extends TestManualSortItemTree
{
    protected function childDisplayMode(): string
    {
        return 'cards';
    }

    protected function leafCardDefinition(): ?CardDefinition
    {
        return CardDefinition::make()
            ->title(CardField::make('name')->hideLabel())
            ->subtitle(CardField::make('status')->hideLabel());
    }
}
