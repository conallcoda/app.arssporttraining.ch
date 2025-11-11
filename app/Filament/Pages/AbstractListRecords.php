<?php


namespace App\Filament\Pages;

use Filament\Resources\Pages\ListRecords;
use App\Filament\Actions\CreateAction;

abstract class AbstractListRecords extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
