<?php

namespace App\Filament\Resources\Athletes\Pages;

use App\Data\Address;
use App\Filament\Pages\AbstractCreateRecord;
use App\Filament\Resources\Athletes\AthleteResource;
use App\Models\Users\Types\Athlete;

class CreateAthlete extends AbstractCreateRecord
{
    protected static string $resource = AthleteResource::class;

    protected function handleRecordCreation(array $data): Athlete
    {
        $data['type'] = 'athlete';
        return Athlete::create($data);
    }
}
