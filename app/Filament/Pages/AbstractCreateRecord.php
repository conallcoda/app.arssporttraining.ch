<?php


namespace App\Filament\Pages;

use Filament\Resources\Pages\CreateRecord;

abstract class AbstractCreateRecord extends CreateRecord
{
    protected static ?string $title = 'Create';

    public function getHeading(): string
    {
        return 'Create';
    }
}
