<?php

namespace App\Filament\Resources\Athletes\Pages;

use App\Filament\Pages\AbstractListRecords;
use App\Filament\Resources\Athletes\AthleteResource;

class ListAthletes extends AbstractListRecords
{
    protected static string $resource = AthleteResource::class;
}
