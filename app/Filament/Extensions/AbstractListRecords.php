<?php


namespace App\Filament\Extensions;

use Filament\Resources\Pages\ListRecords;
use App\Filament\Extensions\Actions\CreateAction;

abstract class AbstractListRecords extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
