<?php

namespace Coda\Cms\Tests\Fixtures;

use Coda\Cms\Display\CardDefinition;
use Coda\Cms\Display\CardField;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;

class TestConfiguredCardList extends TestCardEnabledList
{
    protected function urlPrefix(): string
    {
        return 'tcl_card_';
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make(),
                Text::make('name')->label('Name'),
                Text::make('status')->label('Status'),
            ])
            ->cardDefinition(
                CardDefinition::make()
                    ->title(CardField::make('name')->hideLabel())
                    ->subtitle(CardField::make('status')->hideLabel())
                    ->meta([
                        CardField::make('status')->label('State')->meta(),
                    ])
            )
            ->defaultSort('name');
    }
}
